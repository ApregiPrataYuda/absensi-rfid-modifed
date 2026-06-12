<?php

namespace App\Http\Controllers;

use App\Models\Konfigurasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GeneralSettingController extends Controller
{
    /**
     * @return array<string, string>
     */
    protected function timezoneOptions(): array
    {
        return [
            'Asia/Jakarta' => 'WIB (UTC+07:00)',
            'Asia/Makassar' => 'WITA (UTC+08:00)',
            'Asia/Jayapura' => 'WIT (UTC+09:00)',
        ];
    }

    public function index(): View
    {
        return view('pages.settings-general', [
            'settings' => $this->getSettings(),
            'timezoneOptions' => $this->timezoneOptions(),
        ]);
    }

    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'website_nama' => ['required', 'string', 'max:120'],
            'website_slogan' => ['nullable', 'string', 'max:150'],
            'website_deskripsi' => ['nullable', 'string', 'max:500'],
            'website_email' => ['nullable', 'email', 'max:120'],
            'website_telepon' => ['nullable', 'string', 'max:40'],
            'website_timezone' => ['required', Rule::in(array_keys($this->timezoneOptions()))],
            'website_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'website_favicon' => ['nullable', 'file', 'mimes:png,ico,svg,webp', 'max:1024'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_favicon' => ['nullable', 'boolean'],
        ]);

        $settings = $this->getSettings();
        $logoPath = (string) ($settings['website_logo_path'] ?? '');
        $faviconPath = (string) ($settings['website_favicon_path'] ?? '');

        if ($request->boolean('remove_logo')) {
            $this->deletePublicFileIfExists($logoPath);
            $logoPath = '';
        }
        if ($request->boolean('remove_favicon')) {
            $this->deletePublicFileIfExists($faviconPath);
            $faviconPath = '';
        }

        if ($request->hasFile('website_logo')) {
            $this->deletePublicFileIfExists($logoPath);
            $logoPath = $request->file('website_logo')->store('settings', 'public');
        }

        if ($request->hasFile('website_favicon')) {
            $this->deletePublicFileIfExists($faviconPath);
            $faviconPath = $request->file('website_favicon')->store('settings', 'public');
        }

        $payload = [
            'website_nama' => trim((string) ($validated['website_nama'] ?? '')),
            'website_slogan' => $this->nullableTrim($validated['website_slogan'] ?? null),
            'website_deskripsi' => $this->nullableTrim($validated['website_deskripsi'] ?? null),
            'website_email' => $this->nullableTrim($validated['website_email'] ?? null),
            'website_telepon' => $this->nullableTrim($validated['website_telepon'] ?? null),
            'website_timezone' => (string) ($validated['website_timezone'] ?? 'Asia/Jakarta'),
            'website_logo_path' => $logoPath,
            'website_favicon_path' => $faviconPath,
        ];

        foreach ($payload as $key => $value) {
            Konfigurasi::query()->updateOrCreate(
                ['key' => $key],
                ['value' => (string) ($value ?? ''), 'keterangan' => 'Pengaturan umum website']
            );
        }

        Cache::forget('app_ui_settings_v1');

        $freshSettings = $this->getSettings();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Pengaturan umum berhasil disimpan.',
                'data' => $freshSettings,
            ]);
        }

        return back()->with('success', 'Pengaturan umum berhasil disimpan.');
    }

    protected function getSettings(): array
    {
        $defaults = [
            'website_nama' => 'E-ABSENSI',
            'website_slogan' => 'School System',
            'website_deskripsi' => '',
            'website_email' => '',
            'website_telepon' => '',
            'website_timezone' => 'Asia/Jakarta',
            'website_logo_path' => '',
            'website_favicon_path' => '',
        ];

        $rows = Konfigurasi::query()
            ->whereIn('key', array_keys($defaults))
            ->pluck('value', 'key')
            ->all();

        $settings = array_merge($defaults, $rows);
        $timezoneOptions = $this->timezoneOptions();
        $websiteTimezone = trim((string) ($settings['website_timezone'] ?? 'Asia/Jakarta'));
        if (!array_key_exists($websiteTimezone, $timezoneOptions)) {
            $websiteTimezone = 'Asia/Jakarta';
        }
        $settings['website_timezone'] = $websiteTimezone;
        $settings['website_timezone_label'] = (string) ($timezoneOptions[$websiteTimezone] ?? 'WIB (UTC+07:00)');
        $settings['website_logo_url'] = !empty($settings['website_logo_path'])
            ? Storage::disk('public')->url($settings['website_logo_path'])
            : null;
        $settings['website_favicon_url'] = !empty($settings['website_favicon_path'])
            ? Storage::disk('public')->url($settings['website_favicon_path'])
            : null;

        return $settings;
    }

    protected function deletePublicFileIfExists(?string $path): void
    {
        $cleanPath = trim((string) $path);
        if ($cleanPath === '') {
            return;
        }

        if (!str_starts_with($cleanPath, 'settings/')) {
            return;
        }

        if (Storage::disk('public')->exists($cleanPath)) {
            Storage::disk('public')->delete($cleanPath);
        }
    }

    protected function nullableTrim($value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $text;
    }
}
