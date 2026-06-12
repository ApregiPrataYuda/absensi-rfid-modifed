<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArsipController extends Controller
{
    public function index(): View
    {
        return view('pages.arsip');
    }

    public function data(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = (int) $request->query('per_page', 10);
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $search = Str::lower(trim((string) $request->query('search', '')));

        $archives = $this->collectArchives();
        if ($search !== '') {
            $archives = $archives
                ->filter(function (array $item) use ($search): bool {
                    $haystack = Str::lower(($item['name'] ?? '') . ' ' . ($item['type'] ?? ''));
                    return Str::contains($haystack, $search);
                })
                ->values();
        }

        $total = $archives->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        if ($page > $lastPage) {
            $page = $lastPage;
        }

        $offset = ($page - 1) * $perPage;
        $rows = $archives
            ->slice($offset, $perPage)
            ->values()
            ->map(function (array $item): array {
                $file = (string) ($item['name'] ?? '');

                return [
                    'name' => $file,
                    'type' => $item['type'] ?? 'Lainnya',
                    'size_label' => $item['size_label'] ?? '0 B',
                    'updated_at_label' => $item['updated_at_label'] ?? '-',
                    'download_url' => route('arsip.download', ['file' => $file]),
                    'destroy_url' => route('arsip.destroy', ['file' => $file]),
                ];
            })
            ->all();

        $from = $total === 0 ? 0 : ($offset + 1);
        $to = $total === 0 ? 0 : min($offset + count($rows), $total);

        return response()->json([
            'success' => true,
            'data' => $rows,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }

    public function download(string $file): StreamedResponse
    {
        $safeName = basename($file);
        $path = 'archives/' . $safeName;

        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->download($path, $safeName);
    }

    public function destroy(Request $request, string $file): RedirectResponse|JsonResponse
    {
        $safeName = basename($file);
        $path = 'archives/' . $safeName;

        if (!Storage::disk('public')->exists($path)) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'File arsip tidak ditemukan.',
                ], 404);
            }

            return back()->with('error', 'File arsip tidak ditemukan.');
        }

        Storage::disk('public')->delete($path);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'File arsip berhasil dihapus.',
            ]);
        }

        return back()->with('success', 'File arsip berhasil dihapus.');
    }

    protected function collectArchives()
    {
        $disk = Storage::disk('public');
        $allowedExtensions = ['csv', 'xlsx', 'xls'];

        return collect($disk->files('archives'))
            ->filter(function (string $path) use ($allowedExtensions): bool {
                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

                return in_array($extension, $allowedExtensions, true);
            })
            ->map(function (string $path) use ($disk): array {
                $name = basename($path);
                $size = $disk->size($path);
                $lastModified = Carbon::createFromTimestamp($disk->lastModified($path));

                return [
                    'name' => $name,
                    'type' => $this->resolveArchiveType($name),
                    'size_bytes' => $size,
                    'size_label' => $this->formatBytes($size),
                    'updated_at' => $lastModified,
                    'updated_at_label' => $lastModified
                        ->timezone(config('app.timezone', 'UTC'))
                        ->format('d M Y H:i'),
                ];
            })
            ->sortByDesc('updated_at')
            ->values();
    }

    protected function resolveArchiveType(string $fileName): string
    {
        $name = Str::lower(pathinfo($fileName, PATHINFO_FILENAME));

        if (Str::endsWith($name, '_absensi')) {
            return 'Absensi';
        }

        if (Str::endsWith($name, '_siswa')) {
            return 'Siswa';
        }

        if (Str::endsWith($name, '_hari_libur')) {
            return 'Hari Libur';
        }

        return 'Lainnya';
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        if ($bytes < 1024 * 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 2) . ' MB';
        }

        return number_format($bytes / (1024 * 1024 * 1024), 2) . ' GB';
    }
}
