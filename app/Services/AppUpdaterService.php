<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ZipArchive;

class AppUpdaterService
{
    public function currentVersion(): string
    {
        $version = trim((string) config('app.version', ''));

        return $version !== '' ? $version : '0.0.0';
    }

    public function check(): array
    {
        try {
            $manifest = $this->fetchManifest();
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Gagal cek update: ' . $this->safeErrorMessage($e),
            ];
        }

        $current = $this->currentVersion();
        $latest = trim((string) ($manifest['latest_version'] ?? ''));
        if ($latest === '') {
            return [
                'success' => false,
                'message' => 'Manifest update tidak valid (latest_version kosong).',
            ];
        }

        return [
            'success' => true,
            'data' => [
                'current_version' => $current,
                'latest_version' => $latest,
                'update_available' => version_compare($latest, $current, '>'),
                'released_at' => $manifest['released_at'] ?? null,
                'notes_html' => $manifest['notes_html'] ?? null,
                'min_php' => $manifest['min_php'] ?? null,
            ],
        ];
    }

    public function installLatest(): array
    {
        $lockHandle = null;
        $updatesRoot = storage_path('app/updates');
        $zipPath = null;
        $stageDir = null;
        $backupDir = null;
        $writtenFiles = [];
        $createdFiles = [];

        try {
            File::ensureDirectoryExists($updatesRoot);

            $lockHandle = fopen($updatesRoot . DIRECTORY_SEPARATOR . 'update.lock', 'c');
            if (!$lockHandle || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
                return [
                    'success' => false,
                    'message' => 'Updater sedang berjalan. Silakan coba lagi beberapa saat.',
                ];
            }

            $current = $this->currentVersion();

            if (function_exists('set_time_limit')) {
                @set_time_limit(0);
            }
            if (function_exists('ignore_user_abort')) {
                @ignore_user_abort(true);
            }

            $this->writeProgress([
                'status' => 'running',
                'step' => 'prepare',
                'message' => 'Menyiapkan update...',
                'percent' => 0,
                'from_version' => $current,
            ]);

            $manifest = $this->fetchManifest();
            $latest = trim((string) ($manifest['latest_version'] ?? ''));
            if ($latest === '') {
                return ['success' => false, 'message' => 'Manifest update tidak valid (latest_version kosong).'];
            }

            if (!version_compare($latest, $current, '>')) {
                $this->writeProgress([
                    'status' => 'done',
                    'step' => 'done',
                    'message' => 'Aplikasi sudah menggunakan versi terbaru.',
                    'percent' => 100,
                    'from_version' => $current,
                    'to_version' => $latest,
                ]);
                return ['success' => true, 'message' => 'Aplikasi sudah menggunakan versi terbaru.', 'data' => [
                    'current_version' => $current,
                    'latest_version' => $latest,
                ]];
            }

            $zipUrl = trim((string) ($manifest['zip_url'] ?? ''));
            $manifestSha = strtolower(trim((string) ($manifest['sha256'] ?? '')));
            $signature = trim((string) ($manifest['signature'] ?? ''));
            if ($zipUrl === '' || $manifestSha === '' || $signature === '') {
                return ['success' => false, 'message' => 'Manifest update tidak lengkap (zip_url/sha256/signature).'];
            }

            if (!preg_match('/^[0-9a-f]{64}$/i', $manifestSha)) {
                return ['success' => false, 'message' => 'Manifest update tidak valid (sha256 bukan format hash).'];
            }

            if (!$this->isAllowedUpdateUrl($zipUrl)) {
                return ['success' => false, 'message' => 'Host update tidak diizinkan.'];
            }

            if (!function_exists('openssl_verify')) {
                return ['success' => false, 'message' => 'Ext openssl tidak tersedia.'];
            }

            if (!class_exists(ZipArchive::class) && !class_exists(\PharData::class)) {
                return ['success' => false, 'message' => 'Ekstensi ZIP/Phar tidak tersedia untuk ekstrak update.'];
            }

            $this->writeProgress([
                'status' => 'running',
                'step' => 'download',
                'message' => 'Mengunduh paket update...',
                'percent' => 0,
                'from_version' => $current,
                'to_version' => $latest,
                'download_percent' => 0,
                'downloaded_bytes' => 0,
                'download_total_bytes' => 0,
            ]);

            $zipPath = $updatesRoot . DIRECTORY_SEPARATOR . 'update_' . $latest . '_' . Str::random(8) . '.zip';
            $this->downloadFile($zipUrl, $zipPath, [
                'from' => $current,
                'to' => $latest,
            ]);

            $this->writeProgress([
                'status' => 'running',
                'step' => 'verify',
                'message' => 'Memverifikasi paket update...',
                'percent' => 72,
                'from_version' => $current,
                'to_version' => $latest,
            ]);

            $downloadSha = strtolower((string) hash_file('sha256', $zipPath));
            if (!hash_equals($manifestSha, $downloadSha)) {
                return ['success' => false, 'message' => 'Checksum update tidak cocok. Unduhan dibatalkan.'];
            }

            if (!$this->verifySignature($manifestSha, $signature)) {
                return ['success' => false, 'message' => 'Signature update tidak valid. Unduhan dibatalkan.'];
            }

            $this->writeProgress([
                'status' => 'running',
                'step' => 'extract',
                'message' => 'Mengekstrak paket update...',
                'percent' => 78,
                'from_version' => $current,
                'to_version' => $latest,
            ]);

            $stageDir = $updatesRoot . DIRECTORY_SEPARATOR . 'stage_' . $latest . '_' . Str::random(8);
            File::ensureDirectoryExists($stageDir);

            $extractedFiles = $this->extractZipSafely($zipPath, $stageDir);
            if ($extractedFiles === []) {
                return ['success' => false, 'message' => 'Paket update kosong atau tidak ada file yang diekstrak.'];
            }

            $backupDir = $updatesRoot . DIRECTORY_SEPARATOR . 'backup_' . $current . '_to_' . $latest . '_' . now()->format('Ymd_His');
            File::ensureDirectoryExists($backupDir);

            $this->writeProgress([
                'status' => 'running',
                'step' => 'maintenance',
                'message' => 'Mengaktifkan mode perawatan...',
                'percent' => 84,
                'from_version' => $current,
                'to_version' => $latest,
            ]);

            $copyFiles = array_values(array_filter($extractedFiles, fn (string $path): bool => !$this->isExcludedPath($path)));

            Artisan::call('down');

            $totalFiles = count($copyFiles);
            $copiedFiles = 0;
            $lastCopyWrite = 0.0;

            foreach ($copyFiles as $relativePath) {
                $source = $stageDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
                $dest = base_path($relativePath);

                if (!is_file($source)) {
                    continue;
                }

                $destDir = dirname($dest);
                File::ensureDirectoryExists($destDir);

                if (file_exists($dest)) {
                    if (!is_writable($dest)) {
                        throw new \RuntimeException('File tidak bisa ditulis: ' . $relativePath);
                    }

                    $backupPath = $backupDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
                    File::ensureDirectoryExists(dirname($backupPath));
                    if (!copy($dest, $backupPath)) {
                        throw new \RuntimeException('Gagal backup file: ' . $relativePath);
                    }
                } else {
                    if (!is_writable($destDir)) {
                        throw new \RuntimeException('Folder tidak bisa ditulis: ' . $relativePath);
                    }
                    $createdFiles[] = $relativePath;
                }

                if (!copy($source, $dest)) {
                    throw new \RuntimeException('Gagal menimpa file: ' . $relativePath);
                }

                $writtenFiles[] = $relativePath;

                $copiedFiles++;
                $now = microtime(true);
                if ($totalFiles > 0 && ($now - $lastCopyWrite) >= 0.35) {
                    $lastCopyWrite = $now;
                    $copyProgress = (int) floor(($copiedFiles / $totalFiles) * 100);
                    $overall = 84 + (int) floor(($copyProgress / 100) * 13);
                    $overall = max(84, min(97, $overall));

                    $this->writeProgress([
                        'status' => 'running',
                        'step' => 'install',
                        'message' => 'Memasang update... (' . $copiedFiles . '/' . $totalFiles . ')',
                        'percent' => $overall,
                        'from_version' => $current,
                        'to_version' => $latest,
                    ]);
                }
            }

            try {
                $this->writeProgress([
                    'status' => 'running',
                    'step' => 'migrate',
                    'message' => 'Menjalankan migrasi database...',
                    'percent' => 98,
                    'from_version' => $current,
                    'to_version' => $latest,
                ]);

                Artisan::call('migrate', ['--force' => true]);
            } catch (\Throwable $e) {
                Log::warning('Updater migrate failed: ' . $e->getMessage(), ['exception' => $e]);
                throw new \RuntimeException('Migrasi database gagal: ' . $this->safeErrorMessage($e));
            }

            Artisan::call('optimize:clear');
            Artisan::call('up');

            File::put($updatesRoot . DIRECTORY_SEPARATOR . 'last_update.json', json_encode([
                'from' => $current,
                'to' => $latest,
                'installed_at' => now()->toISOString(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $this->pruneOldBackups($updatesRoot, $backupDir);

            $this->writeProgress([
                'status' => 'done',
                'step' => 'done',
                'message' => 'Update berhasil dipasang.',
                'percent' => 100,
                'from_version' => $current,
                'to_version' => $latest,
            ]);

            return [
                'success' => true,
                'message' => 'Update berhasil dipasang.',
                'data' => [
                    'from' => $current,
                    'to' => $latest,
                ],
            ];
        } catch (\Throwable $e) {
            try {
                Artisan::call('up');
            } catch (\Throwable $upErr) {
                // ignore
            }

            if ($backupDir && $writtenFiles !== []) {
                $this->rollbackFiles($backupDir, $writtenFiles, $createdFiles);
            }

            Log::error('Updater failed: ' . $e->getMessage(), ['exception' => $e]);

            $this->writeProgress([
                'status' => 'error',
                'step' => 'error',
                'message' => 'Update gagal: ' . $this->safeErrorMessage($e),
            ]);

            return [
                'success' => false,
                'message' => 'Update gagal: ' . $this->safeErrorMessage($e),
            ];
        } finally {
            if (is_resource($lockHandle)) {
                @flock($lockHandle, LOCK_UN);
                @fclose($lockHandle);
            }
            if (is_string($zipPath) && $zipPath !== '' && file_exists($zipPath)) {
                @unlink($zipPath);
            }
            if (is_string($stageDir) && $stageDir !== '' && is_dir($stageDir)) {
                File::deleteDirectory($stageDir);
            }
        }
    }

    protected function fetchManifest(): array
    {
        $manifestUrl = trim((string) config('updater.manifest_url', ''));
        if ($manifestUrl === '') {
            throw new \RuntimeException('URL update server belum dikonfigurasi.');
        }

        $timeout = (int) config('updater.timeout', 20);
        $connectTimeout = (int) config('updater.connect_timeout', 10);

        $request = Http::timeout($timeout)
            ->withOptions([
                'connect_timeout' => $connectTimeout,
            ])
            ->acceptJson()
            ->asJson();

        $license = config('updater.license_key');
        if (is_string($license) && trim($license) !== '') {
            $request = $request->withHeaders([
                'X-Update-License' => trim($license),
            ]);
        }

        $response = $request->get($manifestUrl);
        if (!$response->successful()) {
            throw new \RuntimeException('Update server merespons HTTP ' . $response->status() . '.');
        }

        $data = $response->json();
        if (!is_array($data)) {
            throw new \RuntimeException('Manifest update bukan JSON object.');
        }

        return $data;
    }

    protected function downloadFile(string $url, string $destPath, array $context = []): void
    {
        $timeout = (int) config('updater.download_timeout', (int) config('updater.timeout', 20));
        $connectTimeout = (int) config('updater.connect_timeout', 10);
        $retries = max(0, (int) config('updater.download_retry', 1));
        $sleepMs = max(0, (int) config('updater.download_retry_sleep_ms', 750));
        $attempts = $retries + 1;

        $from = trim((string) ($context['from'] ?? ''));
        $to = trim((string) ($context['to'] ?? ''));

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            if (is_file($destPath)) {
                @unlink($destPath);
            }

            try {
                $lastWrite = 0.0;
                $lastPercent = -1;

                $request = Http::timeout($timeout)
                    ->withOptions([
                        'connect_timeout' => $connectTimeout,
                        'progress' => function ($downloadTotal, $downloadedBytes, $uploadTotal, $uploadedBytes) use (&$lastWrite, &$lastPercent, $from, $to): void {
                            $downloadTotal = (int) $downloadTotal;
                            $downloadedBytes = (int) $downloadedBytes;
                            if ($downloadedBytes < 0) {
                                $downloadedBytes = 0;
                            }

                            $percent = 0;
                            $overall = null;
                            if ($downloadTotal > 0) {
                                $percent = (int) floor(($downloadedBytes / $downloadTotal) * 100);
                                $percent = max(0, min(100, $percent));
                                $overall = (int) floor($percent * 0.7);
                            }

                            $now = microtime(true);
                            if ($percent === $lastPercent && ($now - $lastWrite) < 0.5) {
                                return;
                            }

                            $lastPercent = $percent;
                            $lastWrite = $now;

                            $payload = [
                                'status' => 'running',
                                'step' => 'download',
                                'message' => 'Mengunduh paket update...',
                                'download_percent' => $percent,
                                'downloaded_bytes' => $downloadedBytes,
                                'download_total_bytes' => max(0, $downloadTotal),
                            ];

                            if ($overall !== null) {
                                $payload['percent'] = max(0, min(70, $overall));
                            }

                            if ($from !== '') {
                                $payload['from_version'] = $from;
                            }
                            if ($to !== '') {
                                $payload['to_version'] = $to;
                            }

                            $this->writeProgress($payload);
                        },
                    ]);

                $license = config('updater.license_key');
                if (is_string($license) && trim($license) !== '') {
                    $request = $request->withHeaders([
                        'X-Update-License' => trim($license),
                    ]);
                }

                $response = $request->sink($destPath)->get($url);
                if ($response->successful()) {
                    $this->writeProgress([
                        'status' => 'running',
                        'step' => 'download',
                        'message' => 'Mengunduh paket update...',
                        'percent' => 70,
                        'from_version' => $from,
                        'to_version' => $to,
                        'download_percent' => 100,
                    ]);
                    return;
                }

                $status = $response->status();
                if ($attempt < $attempts && ($status === 429 || $status >= 500)) {
                    usleep($sleepMs * 1000);
                    continue;
                }

                throw new \RuntimeException('Gagal download update. HTTP ' . $status . '.');
            } catch (\Throwable $e) {
                if (is_file($destPath)) {
                    @unlink($destPath);
                }

                if ($attempt >= $attempts) {
                    throw $e;
                }

                usleep($sleepMs * 1000);
            }
        }
    }

    protected function verifySignature(string $sha256Hex, string $signatureBase64): bool
    {
        $publicKey = $this->normalizePublicKey((string) config('updater.public_key', ''));
        if ($publicKey === '') {
            return false;
        }

        $signature = base64_decode($signatureBase64, true);
        if ($signature === false) {
            return false;
        }

        $result = openssl_verify($sha256Hex, $signature, $publicKey, OPENSSL_ALGO_SHA256);

        return $result === 1;
    }

    protected function normalizePublicKey(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        $raw = str_replace(["\\r", "\\n"], ["\r", "\n"], $raw);

        if (str_contains($raw, 'BEGIN PUBLIC KEY') || str_contains($raw, 'BEGIN RSA PUBLIC KEY')) {
            return $raw;
        }

        $decoded = base64_decode($raw, true);
        if ($decoded !== false && (str_contains($decoded, 'BEGIN PUBLIC KEY') || str_contains($decoded, 'BEGIN RSA PUBLIC KEY'))) {
            return $decoded;
        }

        return '';
    }

    /**
     * @return array<int, string> relative file paths
     */
    protected function extractZipSafely(string $zipPath, string $stageDir): array
    {
        if (class_exists(ZipArchive::class)) {
            return $this->extractZipArchiveSafely($zipPath, $stageDir);
        }

        // Fallback PharData (jika ZIP tidak ada)
        if (!class_exists(\PharData::class)) {
            throw new \RuntimeException('Ekstensi ZIP tidak tersedia.');
        }

        $phar = new \PharData($zipPath);
        $phar->extractTo($stageDir, null, true);

        return $this->listStageFiles($stageDir);
    }

    /**
     * @return array<int, string>
     */
    protected function extractZipArchiveSafely(string $zipPath, string $stageDir): array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('Gagal membuka paket ZIP.');
        }

        $rootPrefix = $this->detectZipRootPrefix($zip);
        $relativeFiles = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            $name = str_replace('\\', '/', $name);
            $name = ltrim($name, '/');

            if ($rootPrefix !== '' && str_starts_with($name, $rootPrefix)) {
                $name = substr($name, strlen($rootPrefix));
            }

            $name = ltrim($name, '/');
            if ($name === '') {
                continue;
            }

            if (preg_match('#(^|/)\\.\\.(?:/|$)#', $name)) {
                $zip->close();
                throw new \RuntimeException('Paket update tidak aman (path traversal).');
            }

            if (str_ends_with($name, '/')) {
                File::ensureDirectoryExists($stageDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name));
                continue;
            }

            $stream = $zip->getStream($zip->getNameIndex($i));
            if ($stream === false) {
                $zip->close();
                throw new \RuntimeException('Gagal ekstrak file dari ZIP: ' . $name);
            }

            $target = $stageDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
            File::ensureDirectoryExists(dirname($target));

            $out = fopen($target, 'w');
            if ($out === false) {
                fclose($stream);
                $zip->close();
                throw new \RuntimeException('Gagal menulis file sementara: ' . $name);
            }

            stream_copy_to_stream($stream, $out);
            fclose($out);
            fclose($stream);

            $relativeFiles[] = $name;
        }

        $zip->close();

        return array_values(array_unique($relativeFiles));
    }

    protected function detectZipRootPrefix(ZipArchive $zip): string
    {
        $rootNames = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            $name = str_replace('\\', '/', $name);
            $name = ltrim($name, '/');
            if ($name === '' || str_starts_with($name, '__MACOSX/')) {
                continue;
            }

            $parts = explode('/', $name);
            if (count($parts) <= 1) {
                return '';
            }

            $rootNames[] = $parts[0];
        }

        $rootNames = array_values(array_unique(array_filter($rootNames)));

        return count($rootNames) === 1 ? ($rootNames[0] . '/') : '';
    }

    /**
     * @return array<int, string>
     */
    protected function listStageFiles(string $stageDir): array
    {
        $files = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($stageDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $fullPath = str_replace('\\', '/', $file->getPathname());
            $base = rtrim(str_replace('\\', '/', $stageDir), '/');
            $rel = ltrim(substr($fullPath, strlen($base)), '/');
            if ($rel !== '') {
                $files[] = $rel;
            }
        }

        return array_values(array_unique($files));
    }

    protected function rollbackFiles(string $backupDir, array $writtenFiles, array $createdFiles): void
    {
        foreach (array_reverse($writtenFiles) as $relativePath) {
            $dest = base_path($relativePath);
            $backupPath = $backupDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

            if (file_exists($backupPath)) {
                @copy($backupPath, $dest);
            } elseif (in_array($relativePath, $createdFiles, true)) {
                @unlink($dest);
            }
        }
    }

    protected function pruneOldBackups(string $updatesRoot, ?string $keepBackupDir): void
    {
        if (!is_string($keepBackupDir) || trim($keepBackupDir) === '') {
            return;
        }

        try {
            $keepReal = realpath($keepBackupDir) ?: $keepBackupDir;
            $keepNorm = strtolower(str_replace('\\', '/', $keepReal));

            foreach (File::directories($updatesRoot) as $dir) {
                $base = basename(str_replace('\\', '/', $dir));
                if (!str_starts_with($base, 'backup_')) {
                    continue;
                }

                $real = realpath($dir) ?: $dir;
                $norm = strtolower(str_replace('\\', '/', $real));
                if ($norm === $keepNorm) {
                    continue;
                }

                try {
                    File::deleteDirectory($dir);
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    protected function isExcludedPath(string $relativePath): bool
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if ($relativePath === '') {
            return true;
        }

        foreach ((array) config('updater.exclude', []) as $excluded) {
            $excluded = (string) $excluded;
            if ($excluded === '') {
                continue;
            }

            $excluded = ltrim(str_replace('\\', '/', $excluded), '/');

            if (str_ends_with($excluded, '/')) {
                if (str_starts_with($relativePath, $excluded)) {
                    return true;
                }
                continue;
            }

            if ($relativePath === $excluded) {
                return true;
            }
        }

        return false;
    }

    protected function isAllowedUpdateUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($scheme === '' || $host === '') {
            return false;
        }

        if ($scheme !== 'https' && !(bool) config('updater.allow_insecure_http', false)) {
            return false;
        }

        $allowedHosts = config('updater.allowed_hosts', []);
        if (!is_array($allowedHosts) || $allowedHosts === []) {
            $manifestHost = strtolower((string) parse_url((string) config('updater.manifest_url', ''), PHP_URL_HOST));
            $allowedHosts = $manifestHost !== '' ? [$manifestHost] : [];
        }

        if ($allowedHosts !== [] && !in_array($host, array_map('strtolower', $allowedHosts), true)) {
            return false;
        }

        return true;
    }

    protected function safeErrorMessage(\Throwable $e): string
    {
        $message = trim($e->getMessage());

        return $message !== '' ? $message : get_class($e);
    }

    protected function progressPath(): string
    {
        return storage_path('app/updates/progress.json');
    }

    protected function writeProgress(array $payload): void
    {
        try {
            $path = $this->progressPath();
            File::ensureDirectoryExists(dirname($path));

            $previous = [];
            if (is_file($path)) {
                $decoded = json_decode((string) file_get_contents($path), true);
                if (is_array($decoded)) {
                    $previous = $decoded;
                }
            }

            $data = array_merge($previous, $payload);

            if (array_key_exists('percent', $data)) {
                $data['percent'] = max(0, min(100, (int) $data['percent']));
            }
            if (array_key_exists('download_percent', $data)) {
                $data['download_percent'] = max(0, min(100, (int) $data['download_percent']));
            }

            $data['updated_at'] = now()->toISOString();

            $json = json_encode($data, JSON_UNESCAPED_SLASHES);
            if (!is_string($json) || $json === '') {
                return;
            }

            file_put_contents($path, $json, LOCK_EX);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
