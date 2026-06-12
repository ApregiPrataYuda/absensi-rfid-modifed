<?php

namespace App\Http\Controllers;

use App\Models\Konfigurasi;
use Database\Seeders\DefaultRoleAccountsSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class InstallController extends Controller
{
    public function requirementsStep(Request $request): View
    {
        return view('install.requirements', [
            'step' => 1,
            'requirements' => $this->systemRequirements(),
        ]);
    }

    public function databaseStep(Request $request): View|RedirectResponse
    {
        $requirements = $this->systemRequirements();
        if (!(bool) ($requirements['ok'] ?? false)) {
            return redirect()->route('install.requirements')->withErrors([
                'requirements' => 'Persyaratan server belum terpenuhi. Aktifkan ekstensi yang dibutuhkan dan pastikan izin folder sudah benar.',
            ]);
        }

        return view('install.database', [
            'step' => 2,
            'values' => [
                'db_host' => '',
                'db_port' => '3306',
                'db_database' => '',
                'db_username' => '',
                'db_password' => '',
            ],
        ]);
    }

    public function storeDatabase(Request $request): RedirectResponse
    {
        $requirements = $this->systemRequirements();
        if (!(bool) ($requirements['ok'] ?? false)) {
            return redirect()
                ->route('install.requirements')
                ->withErrors([
                    'requirements' => 'Persyaratan server belum terpenuhi. Aktifkan ekstensi yang dibutuhkan dan pastikan izin folder sudah benar.',
                ]);
        }

        $validated = $request->validate([
            'db_host' => ['required', 'string', 'max:255'],
            'db_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'db_database' => ['required', 'string', 'max:255'],
            'db_username' => ['required', 'string', 'max:255'],
            'db_password' => ['nullable', 'string', 'max:255'],
        ]);

        $dbSettings = [
            'db_host' => trim((string) $validated['db_host']),
            'db_port' => (string) $validated['db_port'],
            'db_database' => trim((string) $validated['db_database']),
            'db_username' => trim((string) $validated['db_username']),
            'db_password' => (string) ($validated['db_password'] ?? ''),
        ];

        try {
            $this->testDatabaseConnection($dbSettings);
        } catch (Throwable $e) {
            return back()
                ->withInput()
                ->withErrors([
                    'db_connection' => 'Gagal konek ke database: ' . $e->getMessage(),
                ]);
        }

        $request->session()->put('installer.db', $dbSettings);

        $this->writeEnvValues([
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $dbSettings['db_host'],
            'DB_PORT' => $dbSettings['db_port'],
            'DB_DATABASE' => $dbSettings['db_database'],
            'DB_USERNAME' => $dbSettings['db_username'],
            'DB_PASSWORD' => $dbSettings['db_password'],
            'APP_INSTALLED' => 'false',
        ]);
        config(['app.installed' => false]);

        return redirect()
            ->route('install.website')
            ->with('success', 'Koneksi database berhasil. Lanjut ke pengaturan website.');
    }

    public function websiteStep(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('installer.db')) {
            return redirect()->route('install.database')->withErrors([
                'db_step' => 'Silakan isi pengaturan database dulu.',
            ]);
        }

        return view('install.website', [
            'step' => 3,
            'values' => [
                'website_nama' => '',
                'website_slogan' => '',
                'website_email' => '',
            ],
        ]);
    }

    public function install(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('installer.db')) {
            return redirect()->route('install.database')->withErrors([
                'db_step' => 'Silakan isi pengaturan database dulu.',
            ]);
        }

        $validated = $request->validate([
            'website_nama' => ['required', 'string', 'max:120'],
            'website_slogan' => ['nullable', 'string', 'max:150'],
            'website_email' => ['nullable', 'email', 'max:120'],
        ]);

        $dbSettings = (array) $request->session()->get('installer.db', []);
        $websiteSettings = [
            'website_nama' => trim((string) $validated['website_nama']),
            'website_slogan' => trim((string) ($validated['website_slogan'] ?? '')),
            'website_email' => trim((string) ($validated['website_email'] ?? '')),
        ];
        $appUrl = $this->detectAppUrl($request);

        try {
            $this->configureRuntimeDatabase($dbSettings);

            Artisan::call('migrate:fresh', [
                '--seed' => true,
                '--force' => true,
            ]);

            try {
                Artisan::call('storage:link', ['--force' => true]);
            } catch (Throwable $e) {
                report($e);
            }

            $this->saveWebsiteSettings($websiteSettings);
            Cache::forget('app_ui_settings_v1');
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $this->writeEnvValues([
                'APP_NAME' => $websiteSettings['website_nama'],
                'APP_URL' => $appUrl,
                'APP_INSTALLED' => 'true',
            ]);
            config(['app.installed' => true]);
            config(['app.url' => $appUrl]);
            Artisan::call('config:clear');
        } catch (Throwable $e) {
            $artisanOutput = trim(Artisan::output());
            $message = 'Instalasi gagal: ' . $e->getMessage();
            if ($artisanOutput !== '') {
                $message .= ' | Output: ' . $artisanOutput;
            }

            return back()
                ->withInput()
                ->withErrors([
                    'install' => $message,
                ]);
        }

        $request->session()->forget('installer');

        return view('install.success', [
            'step' => 4,
            'websiteName' => $websiteSettings['website_nama'],
            'accounts' => DefaultRoleAccountsSeeder::defaultAccounts(),
            'loginUrl' => route('login'),
        ]);
    }

    private function configureRuntimeDatabase(array $dbSettings): void
    {
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => (string) ($dbSettings['db_host'] ?? env('DB_HOST', '127.0.0.1')),
            'database.connections.mysql.port' => (string) ($dbSettings['db_port'] ?? env('DB_PORT', '3306')),
            'database.connections.mysql.database' => (string) ($dbSettings['db_database'] ?? env('DB_DATABASE', '')),
            'database.connections.mysql.username' => (string) ($dbSettings['db_username'] ?? env('DB_USERNAME', 'root')),
            'database.connections.mysql.password' => (string) ($dbSettings['db_password'] ?? env('DB_PASSWORD', '')),
        ]);

        DB::purge('mysql');
        DB::reconnect('mysql');
    }

    private function testDatabaseConnection(array $dbSettings): void
    {
        $installerConnection = 'installer_check';
        $mysqlConfig = (array) config('database.connections.mysql', []);
        $checkConfig = array_merge($mysqlConfig, [
            'host' => (string) ($dbSettings['db_host'] ?? '127.0.0.1'),
            'port' => (string) ($dbSettings['db_port'] ?? '3306'),
            'database' => (string) ($dbSettings['db_database'] ?? ''),
            'username' => (string) ($dbSettings['db_username'] ?? ''),
            'password' => (string) ($dbSettings['db_password'] ?? ''),
        ]);

        config(["database.connections.{$installerConnection}" => $checkConfig]);

        DB::purge($installerConnection);
        DB::connection($installerConnection)->getPdo();
        DB::disconnect($installerConnection);
    }

    private function saveWebsiteSettings(array $websiteSettings): void
    {
        $payload = [
            'website_nama' => $websiteSettings['website_nama'],
            'website_slogan' => $websiteSettings['website_slogan'],
            'website_email' => $websiteSettings['website_email'],
            'website_deskripsi' => '',
            'website_telepon' => '',
        ];

        foreach ($payload as $key => $value) {
            Konfigurasi::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => (string) $value,
                    'keterangan' => 'Pengaturan umum website',
                ]
            );
        }
    }

    private function writeEnvValues(array $values): void
    {
        $envPath = base_path('.env');
        $this->ensureEnvFileExists($envPath);
        $content = File::exists($envPath) ? (string) File::get($envPath) : '';

        foreach ($values as $key => $value) {
            $key = trim((string) $key);
            $formatted = $this->formatEnvValue($value);
            $pattern = "/^" . preg_quote($key, '/') . "=.*$/m";

            if (preg_match($pattern, $content) === 1) {
                $content = preg_replace($pattern, "{$key}={$formatted}", $content) ?? $content;
            } else {
                $content = rtrim($content) . PHP_EOL . "{$key}={$formatted}" . PHP_EOL;
            }
        }

        File::put($envPath, $content);
    }

    private function formatEnvValue($value): string
    {
        if ($value === null) {
            return '';
        }

        $text = (string) $value;
        if ($text === '') {
            return '';
        }

        if (preg_match('/\s|#|"|=/', $text)) {
            return '"' . str_replace('"', '\"', $text) . '"';
        }

        return $text;
    }

    private function detectAppUrl(Request $request): string
    {
        $root = rtrim((string) $request->root(), '/');
        if ($root === '') {
            $root = rtrim((string) config('app.url', 'http://localhost'), '/');
        }

        if ((bool) env('APP_FORCE_HTTPS', false) && str_starts_with($root, 'http://')) {
            $root = 'https://' . substr($root, 7);
        }

        return $root;
    }

    private function ensureEnvFileExists(string $envPath): void
    {
        if (File::exists($envPath)) {
            return;
        }

        $examplePath = base_path('.env.example');
        if (File::exists($examplePath)) {
            File::copy($examplePath, $envPath);
            return;
        }

        File::put($envPath, '');
    }

    private function systemRequirements(): array
    {
        $requiredPhp = '8.2.0';
        $phpCurrent = PHP_VERSION;
        $phpOk = version_compare($phpCurrent, $requiredPhp, '>=');

        $zipOk = class_exists(\ZipArchive::class);
        $pharOk = class_exists(\PharData::class);

        $extensions = [
            [
                'key' => 'curl',
                'label' => 'cURL (wajib untuk updater)',
                'ok' => extension_loaded('curl'),
            ],
            [
                'key' => 'openssl',
                'label' => 'OpenSSL',
                'ok' => extension_loaded('openssl'),
            ],
            [
                'key' => 'mbstring',
                'label' => 'Mbstring',
                'ok' => extension_loaded('mbstring'),
            ],
            [
                'key' => 'tokenizer',
                'label' => 'Tokenizer',
                'ok' => extension_loaded('tokenizer'),
            ],
            [
                'key' => 'xml',
                'label' => 'XML',
                'ok' => extension_loaded('xml'),
            ],
            [
                'key' => 'ctype',
                'label' => 'Ctype',
                'ok' => extension_loaded('ctype'),
            ],
            [
                'key' => 'json',
                'label' => 'JSON',
                'ok' => extension_loaded('json'),
            ],
            [
                'key' => 'fileinfo',
                'label' => 'Fileinfo',
                'ok' => extension_loaded('fileinfo'),
            ],
            [
                'key' => 'pdo',
                'label' => 'PDO',
                'ok' => extension_loaded('pdo'),
            ],
            [
                'key' => 'pdo_mysql',
                'label' => 'PDO MySQL',
                'ok' => extension_loaded('pdo_mysql'),
            ],
            [
                'key' => 'zip_or_phar',
                'label' => 'ZIP / Phar (wajib untuk ekstrak update)',
                'ok' => $zipOk || $pharOk,
                'detail' => [
                    'zip' => $zipOk,
                    'phar' => $pharOk,
                ],
            ],
        ];

        $envPath = base_path('.env');
        $envOk = is_file($envPath)
            ? is_writable($envPath)
            : is_writable(dirname($envPath));

        $storageOk = is_dir(storage_path()) && is_writable(storage_path());

        $bootstrapCachePath = base_path('bootstrap/cache');
        $bootstrapCacheOk = is_dir($bootstrapCachePath) && is_writable($bootstrapCachePath);

        $permissions = [
            [
                'key' => 'env',
                'label' => '.env (writable)',
                'ok' => $envOk,
            ],
            [
                'key' => 'storage',
                'label' => 'storage/ (writable)',
                'ok' => $storageOk,
            ],
            [
                'key' => 'bootstrap_cache',
                'label' => 'bootstrap/cache (writable)',
                'ok' => $bootstrapCacheOk,
            ],
        ];

        $extensionsOk = true;
        foreach ($extensions as $ext) {
            if (!(bool) ($ext['ok'] ?? false)) {
                $extensionsOk = false;
                break;
            }
        }

        $permissionsOk = true;
        foreach ($permissions as $perm) {
            if (!(bool) ($perm['ok'] ?? false)) {
                $permissionsOk = false;
                break;
            }
        }

        return [
            'ok' => $phpOk && $extensionsOk && $permissionsOk,
            'php' => [
                'current' => $phpCurrent,
                'required' => $requiredPhp,
                'ok' => $phpOk,
            ],
            'extensions' => $extensions,
            'permissions' => $permissions,
        ];
    }
}
