<?php

namespace App\Providers;

use App\Models\Device;
use App\Models\IzinSakitRequest;
use App\Models\Konfigurasi;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->isInstallerMode()) {
            config([
                'session.driver' => 'file',
                'cache.default' => 'file',
                'queue.default' => 'sync',
            ]);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $defaultConnection = config('database.default');
        $defaultDriver = is_string($defaultConnection)
            ? config("database.connections.{$defaultConnection}.driver")
            : null;

        if (in_array($defaultDriver, ['mysql', 'mariadb'], true)) {
            // Shared hosting often still uses legacy MySQL index limits.
            Schema::defaultStringLength(191);
        }

        if ((bool) env('APP_FORCE_HTTPS', false)) {
            URL::forceScheme('https');
        }

        RateLimiter::for('device-attendance', function (Request $request): Limit {
            $device = $request->attributes->get('device');

            return Limit::perMinute(120)->by(
                $device instanceof Device
                    ? 'device:' . $device->id
                    : ((string) $request->bearerToken() !== '' ? 'token:' . $request->bearerToken() : 'ip:' . $request->ip())
            );
        });

        $settings = [
            'website_nama' => 'E-ABSENSI',
            'website_slogan' => 'School System',
            'website_deskripsi' => 'Sistem Absensi Pintar',
            'website_email' => '',
            'website_telepon' => '',
            'website_timezone' => 'Asia/Jakarta',
            'website_logo_path' => '',
            'website_favicon_path' => '',
            'website_logo_url' => null,
            'website_favicon_url' => null,
        ];

        try {
            if (Schema::hasTable('konfigurasi')) {
                $settings = Cache::remember('app_ui_settings_v1', 300, function () use ($settings) {
                    $rows = Konfigurasi::query()
                        ->whereIn('key', [
                            'website_nama',
                            'website_slogan',
                            'website_deskripsi',
                            'website_email',
                            'website_telepon',
                            'website_timezone',
                            'website_logo_path',
                            'website_favicon_path',
                        ])
                        ->pluck('value', 'key')
                        ->all();

                    $final = array_merge($settings, $rows);
                    $final['website_logo_url'] = !empty($final['website_logo_path'])
                        ? Storage::disk('public')->url($final['website_logo_path'])
                        : null;
                    $final['website_favicon_url'] = !empty($final['website_favicon_path'])
                        ? Storage::disk('public')->url($final['website_favicon_path'])
                        : null;

                    return $final;
                });
            }
        } catch (\Throwable $e) {
            // Keep defaults when database is not available during installer mode.
        }

        $websiteTimezone = $this->resolveWebsiteTimezone((string) ($settings['website_timezone'] ?? 'Asia/Jakarta'));
        $settings['website_timezone'] = $websiteTimezone;
        $settings['website_timezone_label'] = $this->timezoneLabel($websiteTimezone);

        config(['app.timezone' => $websiteTimezone]);
        try {
            date_default_timezone_set($websiteTimezone);
        } catch (\Throwable $e) {
            // Abaikan jika timezone tidak valid.
        }

        View::share('appUiSettings', $settings);

        View::composer('partials.sidebar', function ($view): void {
            $view->with('sidebarIzinSakitPendingCount', $this->resolveSidebarIzinSakitPendingCount());
        });
    }

    private function isInstallerMode(): bool
    {
        return !(bool) config('app.installed', true);
    }

    /**
     * @return array<string, string>
     */
    private function timezoneOptions(): array
    {
        return [
            'Asia/Jakarta' => 'WIB (UTC+07:00)',
            'Asia/Makassar' => 'WITA (UTC+08:00)',
            'Asia/Jayapura' => 'WIT (UTC+09:00)',
        ];
    }

    private function resolveWebsiteTimezone(string $timezone): string
    {
        $candidate = trim($timezone);
        $options = $this->timezoneOptions();

        if ($candidate !== '' && array_key_exists($candidate, $options)) {
            return $candidate;
        }

        return 'Asia/Jakarta';
    }

    private function timezoneLabel(string $timezone): string
    {
        $options = $this->timezoneOptions();

        return (string) ($options[$timezone] ?? 'WIB (UTC+07:00)');
    }

    private function resolveSidebarIzinSakitPendingCount(): int
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return 0;
            }

            $canManage = $user->can('izin-sakit.manage');
            $canApprove = $canManage || $user->can('izin-sakit.approve');
            $canRequest = $canManage || $user->can('izin-sakit.request');

            if (!$canRequest && !$canApprove) {
                return 0;
            }

            $query = IzinSakitRequest::query()
                ->where('status', IzinSakitRequest::STATUS_PENDING);

            $userId = (int) ($user->id ?? 0);
            if (!$canManage) {
                if ($user->hasRole('wakel')) {
                    $kelasWakel = trim((string) ($user->kelas ?? ''));
                    if ($kelasWakel !== '') {
                        $query->whereHas('siswa', fn ($siswaQuery) => $siswaQuery->where('kelas', $kelasWakel));
                    } elseif ($userId > 0) {
                        $query->where('requested_by_user_id', $userId);
                    } else {
                        return 0;
                    }
                } elseif (!$canApprove && $userId > 0) {
                    $query->where('requested_by_user_id', $userId);
                } elseif (!$canApprove) {
                    return 0;
                }
            }

            return (int) $query->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
