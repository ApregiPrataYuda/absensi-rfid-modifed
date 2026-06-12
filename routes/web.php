<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataGuruController;
use App\Http\Controllers\DataPiketController;
use App\Http\Controllers\DataSiswaController;
use App\Http\Controllers\AbsensiPelajaranController;
use App\Http\Controllers\ArsipController;
use App\Http\Controllers\BackupRestoreSettingController;
use App\Http\Controllers\DeviceSettingController;
use App\Http\Controllers\GeneralSettingController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\KartuAbsensiController;
use App\Http\Controllers\KelolaAbsenController;
use App\Http\Controllers\KelolaKelasController;
use App\Http\Controllers\KartuSiswaController;
use App\Http\Controllers\KenaikanKelasController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\NotificationSettingController;
use App\Http\Controllers\PoinPelanggaranController;
use App\Http\Controllers\ProfileSettingController;
use App\Http\Controllers\JadwalPelajaranController;
use App\Http\Controllers\JurnalMengajarHarianController;
use App\Http\Controllers\IzinSakitRequestController;
use App\Http\Controllers\RekapAbsensiController;
use App\Http\Controllers\RekapAbsensiPelajaranController;
use App\Http\Controllers\RekapBulananController;
use App\Http\Controllers\RekapTahunanController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\ScannerController;
use App\Http\Controllers\SiswaMataPelajaranController;
use App\Http\Controllers\SiswaPresensiController;
use App\Http\Controllers\TabunganSiswaController;
use App\Http\Controllers\UpdateSettingController;
use Illuminate\Support\Facades\Route;

Route::get('/install', [InstallController::class, 'requirementsStep'])->name('install.requirements');
Route::get('/install/database', [InstallController::class, 'databaseStep'])->name('install.database');
Route::post('/install/database', [InstallController::class, 'storeDatabase'])->name('install.database.store');
Route::get('/install/website', [InstallController::class, 'websiteStep'])->name('install.website');
Route::post('/install/website', [InstallController::class, 'install'])->name('install.website.store');

Route::get('/', function () {
    return redirect()->route('dashboard');
})->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('permission:dashboard.view')->name('dashboard');
    Route::get('/data-karyawan', [DataSiswaController::class, 'index'])->middleware('permission:siswa.view')->name('data-siswa');
    Route::get('/data-siswa/card-capture-stream', [KartuAbsensiController::class, 'captureStream'])
        ->middleware('permission:siswa.view')
        ->name('data-siswa.card-capture-stream');
    Route::get('/data-foreman', [DataGuruController::class, 'index'])->middleware('permission:guru.view')->name('data-guru');
    Route::get('/data-piket', [DataPiketController::class, 'index'])->middleware('permission:piket.view')->name('data-piket');
    Route::get('/jadwal-libur', [KelolaAbsenController::class, 'index'])->middleware('permission:absen.manage')->name('kelola-absen');
    Route::redirect('/kelola-absen', '/jadwal-libur')->middleware('permission:absen.manage');
    Route::middleware('permission:kartu-absensi.manage')
        ->prefix('kartu-absensi')
        ->name('kartu-absensi.')
        ->group(function (): void {
            Route::get('/', [KartuAbsensiController::class, 'index'])->name('index');
            Route::get('/data', [KartuAbsensiController::class, 'data'])->name('data');
            Route::get('/stream', [KartuAbsensiController::class, 'stream'])->name('stream');
            Route::post('/', [KartuAbsensiController::class, 'store'])->name('store');
            Route::put('/{kartuAbsensi}', [KartuAbsensiController::class, 'update'])->name('update');
            Route::delete('/{kartuAbsensi}', [KartuAbsensiController::class, 'destroy'])->name('destroy');
        });

    Route::middleware('permission:kelas.manage')->group(function () {
        Route::get('/kelola-kelas', [KelolaKelasController::class, 'index'])->name('kelola-kelas');
        Route::get('/kelola-kelas/data', [KelolaKelasController::class, 'data'])->name('kelola-kelas.data');
        Route::post('/kelola-kelas', [KelolaKelasController::class, 'store'])->name('kelola-kelas.store');
        Route::put('/kelola-kelas/{kelas}', [KelolaKelasController::class, 'update'])->name('kelola-kelas.update');
        Route::delete('/kelola-kelas/{kelas}', [KelolaKelasController::class, 'destroy'])->name('kelola-kelas.destroy');
    });
    Route::middleware('permission:jadwal-pelajaran.manage')
        ->prefix('jadwal-pelajaran')
        ->name('jadwal-pelajaran.')
        ->group(function (): void {
            Route::get('/', [JadwalPelajaranController::class, 'index'])->name('index');
            Route::get('/data', [JadwalPelajaranController::class, 'data'])->name('data');
            Route::post('/', [JadwalPelajaranController::class, 'store'])->name('store');
            Route::put('/{jadwalPelajaran}', [JadwalPelajaranController::class, 'update'])->name('update');
            Route::delete('/{jadwalPelajaran}', [JadwalPelajaranController::class, 'destroy'])->name('destroy');
        });
    Route::middleware('permission:jurnal-mengajar.manage')
        ->prefix('jurnal-mengajar')
        ->name('jurnal-mengajar.')
        ->group(function (): void {
            Route::get('/', [JurnalMengajarHarianController::class, 'index'])->name('index');
            Route::get('/data', [JurnalMengajarHarianController::class, 'data'])->name('data');
            Route::post('/', [JurnalMengajarHarianController::class, 'store'])->name('store');
            Route::put('/{jurnalMengajar}', [JurnalMengajarHarianController::class, 'update'])->name('update');
            Route::delete('/{jurnalMengajar}', [JurnalMengajarHarianController::class, 'destroy'])->name('destroy');
        });
    Route::middleware('permission:izin-sakit.request|izin-sakit.approve|izin-sakit.manage')
        ->prefix('izin-sakit')
        ->name('izin-sakit.')
        ->group(function (): void {
            Route::get('/', [IzinSakitRequestController::class, 'index'])->name('index');
            Route::get('/data', [IzinSakitRequestController::class, 'data'])->name('data');
            Route::post('/', [IzinSakitRequestController::class, 'store'])->name('store');
            Route::put('/{izinSakitRequest}/approve', [IzinSakitRequestController::class, 'approve'])
                ->middleware('permission:izin-sakit.approve|izin-sakit.manage')
                ->name('approve');
            Route::put('/{izinSakitRequest}/reject', [IzinSakitRequestController::class, 'reject'])
                ->middleware('permission:izin-sakit.approve|izin-sakit.manage')
                ->name('reject');
            Route::delete('/{izinSakitRequest}', [IzinSakitRequestController::class, 'destroy'])->name('destroy');
        });
    Route::middleware('permission:poin-pelanggaran.view|poin-pelanggaran.manage')
        ->prefix('poin-pelanggaran')
        ->name('poin-pelanggaran.')
        ->group(function (): void {
            Route::get('/', [PoinPelanggaranController::class, 'index'])->name('index');
            Route::get('/master', [PoinPelanggaranController::class, 'masterPage'])->name('master.index');
            Route::get('/riwayat', [PoinPelanggaranController::class, 'riwayatPage'])->name('riwayat.index');
            Route::get('/data', [PoinPelanggaranController::class, 'data'])->name('data');
            Route::post('/jenis', [PoinPelanggaranController::class, 'storeJenis'])
                ->middleware('permission:poin-pelanggaran.manage')
                ->name('jenis.store');
            Route::put('/jenis/{jenisPelanggaran}', [PoinPelanggaranController::class, 'updateJenis'])
                ->middleware('permission:poin-pelanggaran.manage')
                ->name('jenis.update');
            Route::delete('/jenis/{jenisPelanggaran}', [PoinPelanggaranController::class, 'destroyJenis'])
                ->middleware('permission:poin-pelanggaran.manage')
                ->name('jenis.destroy');
            Route::post('/riwayat', [PoinPelanggaranController::class, 'storePelanggaran'])
                ->middleware('permission:poin-pelanggaran.manage')
                ->name('riwayat.store');
            Route::put('/riwayat/{poinPelanggaran}', [PoinPelanggaranController::class, 'updatePelanggaran'])
                ->middleware('permission:poin-pelanggaran.manage')
                ->name('riwayat.update');
            Route::delete('/riwayat/{poinPelanggaran}', [PoinPelanggaranController::class, 'destroyPelanggaran'])
                ->middleware('permission:poin-pelanggaran.manage')
                ->name('riwayat.destroy');
        });
    Route::middleware('permission:tabungan-siswa.view|tabungan-siswa.manage|tabungan-siswa.report|tabungan-siswa.jenis.manage')
        ->prefix('tabungan-siswa')
        ->name('tabungan-siswa.')
        ->group(function (): void {
            Route::get('/', [TabunganSiswaController::class, 'index'])->name('index');
            Route::get('/jenis', [TabunganSiswaController::class, 'jenisPage'])->name('jenis.index');
            Route::get('/jenis/data', [TabunganSiswaController::class, 'jenisData'])->name('jenis.data');
            Route::post('/jenis', [TabunganSiswaController::class, 'storeType'])
                ->middleware('permission:tabungan-siswa.jenis.manage')
                ->name('jenis.store');
            Route::put('/jenis/{jenisTabungan}', [TabunganSiswaController::class, 'updateType'])
                ->middleware('permission:tabungan-siswa.jenis.manage')
                ->name('jenis.update');
            Route::delete('/jenis/{jenisTabungan}', [TabunganSiswaController::class, 'destroyType'])
                ->middleware('permission:tabungan-siswa.jenis.manage')
                ->name('jenis.destroy');
            Route::get('/rekening', [TabunganSiswaController::class, 'rekeningPage'])->name('rekening.index');
            Route::get('/rekening/data', [TabunganSiswaController::class, 'rekeningData'])->name('rekening.data');
            Route::get('/rekening/{account}/riwayat', [TabunganSiswaController::class, 'rekeningHistory'])->name('rekening.riwayat');
            Route::post('/rekening', [TabunganSiswaController::class, 'storeAccount'])
                ->middleware('permission:tabungan-siswa.manage')
                ->name('rekening.store');
            Route::put('/rekening/{account}', [TabunganSiswaController::class, 'updateAccount'])
                ->middleware('permission:tabungan-siswa.manage')
                ->name('rekening.update');
            Route::delete('/rekening/{account}', [TabunganSiswaController::class, 'destroyAccount'])
                ->middleware('permission:tabungan-siswa.manage')
                ->name('rekening.destroy');
            Route::get('/transaksi/data', [TabunganSiswaController::class, 'transaksiData'])->name('transaksi.data');
            Route::post('/transaksi', [TabunganSiswaController::class, 'storeTransaction'])
                ->middleware('permission:tabungan-siswa.manage')
                ->name('transaksi.store');
            Route::put('/transaksi/{transaction}', [TabunganSiswaController::class, 'updateTransaction'])
                ->middleware('permission:tabungan-siswa.manage')
                ->name('transaksi.update');
            Route::delete('/transaksi/{transaction}', [TabunganSiswaController::class, 'destroyTransaction'])
                ->middleware('permission:tabungan-siswa.manage')
                ->name('transaksi.destroy');
        });
    Route::get('/tabungan-siswa/transaksi/{transaction}/print', [TabunganSiswaController::class, 'printTransaction'])
        ->middleware('permission:tabungan-siswa.view|tabungan-siswa.manage|tabungan-siswa.report|tabungan-siswa.jenis.manage|tabungan-siswa.self.view')
        ->name('tabungan-siswa.transaksi.print');
    Route::get('/tabungan-siswa/rekening/{account}/rekening-koran', [TabunganSiswaController::class, 'printAccountStatement'])
        ->middleware('permission:tabungan-siswa.view|tabungan-siswa.manage|tabungan-siswa.report|tabungan-siswa.jenis.manage|tabungan-siswa.self.view')
        ->name('tabungan-siswa.rekening.statement');
    Route::get('/kenaikan-kelas', [KenaikanKelasController::class, 'index'])->middleware('permission:kenaikan-kelas.manage')->name('kenaikan-kelas');
    Route::middleware('permission:arsip.manage')
        ->prefix('arsip')
        ->name('arsip.')
        ->group(function () {
            Route::get('/', [ArsipController::class, 'index'])->name('index');
            Route::get('/data', [ArsipController::class, 'data'])->name('data');
            Route::get('/{file}/download', [ArsipController::class, 'download'])->name('download');
            Route::delete('/{file}', [ArsipController::class, 'destroy'])->name('destroy');
        });

    Route::get('/monitoring', [MonitoringController::class, 'index'])->middleware('permission:monitoring.view')->name('monitoring');
    Route::get('/rekap-bulanan', [RekapBulananController::class, 'index'])->middleware('permission:rekap-bulanan.view')->name('rekap-bulanan');
    Route::get('/rekap-tahunan', [RekapTahunanController::class, 'index'])->middleware('permission:rekap-tahunan.view')->name('rekap-tahunan');
    Route::get('/laporan-absensi', [RekapAbsensiController::class, 'index'])->middleware('permission:rekap-absensi.view')->name('rekap-absensi');
    Route::redirect('/rekap-absensi', '/laporan-absensi')->middleware('permission:rekap-absensi.view');
    Route::get('/laporan-absensi-pelajaran', [RekapAbsensiPelajaranController::class, 'index'])->middleware('permission:rekap-absensi-pelajaran.view')->name('rekap-absensi-pelajaran');
    Route::get('/scanner', [ScannerController::class, 'index'])->middleware('permission:scanner.use')->name('scanner');
    Route::get('/absensi-pelajaran', [AbsensiPelajaranController::class, 'index'])->middleware('permission:scanner.use')->name('absensi-pelajaran');
    Route::get('/kartu-karyawan', [KartuSiswaController::class, 'index'])->middleware('permission:kartu-siswa.view')->name('kartu-siswa');
    Route::get('/mata-pelajaran-saya', [SiswaMataPelajaranController::class, 'index'])->name('mata-pelajaran-saya');
    Route::get('/presensi-saya', [SiswaPresensiController::class, 'index'])->name('presensi-saya');
    Route::get('/tabungan-saya', [TabunganSiswaController::class, 'selfIndex'])
        ->middleware('permission:tabungan-siswa.self.view')
        ->name('tabungan-saya');

    Route::middleware('permission:settings.roles.manage')
        ->prefix('manajemen-role')
        ->name('role-permission.')
        ->group(function () {
            Route::get('/', [RolePermissionController::class, 'index'])->name('index');
            Route::post('/roles', [RolePermissionController::class, 'storeRole'])->name('store-role');
            Route::put('/roles/{role}/permissions', [RolePermissionController::class, 'syncPermissions'])->name('sync');
            Route::delete('/roles/{role}', [RolePermissionController::class, 'destroyRole'])->name('destroy-role');
        });

    Route::middleware('permission:settings.users.manage')
        ->prefix('data-user')
        ->name('role-permission.users.')
        ->group(function () {
            Route::get('/', [RolePermissionController::class, 'usersIndex'])->name('index');
            Route::post('/', [RolePermissionController::class, 'storeAdminUser'])->name('store');
            Route::put('/{user}', [RolePermissionController::class, 'updateAdminUser'])->name('update');
            Route::delete('/{user}', [RolePermissionController::class, 'destroyAdminUser'])->name('destroy');
        });

    Route::middleware('permission:settings.general.manage')
        ->prefix('settings/general')
        ->name('settings.general.')
        ->group(function () {
            Route::get('/', [GeneralSettingController::class, 'index'])->name('index');
            Route::post('/', [GeneralSettingController::class, 'update'])->name('update');
        });

    Route::middleware('permission:settings.devices.manage')
        ->prefix('settings/devices')
        ->name('settings.devices.')
        ->group(function () {
            Route::get('/', [DeviceSettingController::class, 'index'])->name('index');
            Route::post('/', [DeviceSettingController::class, 'store'])->name('store');
            Route::put('/{device}/activate', [DeviceSettingController::class, 'activate'])->name('activate');
            Route::put('/{device}/deactivate', [DeviceSettingController::class, 'deactivate'])->name('deactivate');
            Route::put('/{device}/revoke', [DeviceSettingController::class, 'revoke'])->name('revoke');
            Route::put('/{device}/reset', [DeviceSettingController::class, 'reset'])->name('reset');
            Route::delete('/{device}', [DeviceSettingController::class, 'destroy'])->name('destroy');
        });

    Route::middleware('permission:settings.notifications.manage')
        ->prefix('settings/notifications')
        ->name('settings.notifications.')
        ->group(function () {
            Route::get('/', [NotificationSettingController::class, 'index'])->name('index');
            Route::post('/', [NotificationSettingController::class, 'update'])->name('update');
            Route::post('/test-send', [NotificationSettingController::class, 'testSend'])->name('test-send');
        });

    Route::middleware('permission:settings.backup.manage')
        ->prefix('settings/backup')
        ->name('settings.backup.')
        ->group(function () {
            Route::get('/', [BackupRestoreSettingController::class, 'index'])->name('index');
            Route::get('/download', [BackupRestoreSettingController::class, 'download'])->name('download');
            Route::post('/restore', [BackupRestoreSettingController::class, 'restore'])->name('restore');
        });

    Route::middleware('permission:notifications.send')
        ->prefix('notifications/send')
        ->name('notifications.send.')
        ->group(function () {
            Route::get('/', [NotificationSettingController::class, 'sendPage'])->name('index');
            Route::post('/', [NotificationSettingController::class, 'sendNotification'])->name('store');
        });

    Route::prefix('settings/profile')
        ->name('settings.profile.')
        ->group(function () {
            Route::get('/', [ProfileSettingController::class, 'index'])->name('index');
            Route::post('/', [ProfileSettingController::class, 'update'])->name('update');
        });

    Route::middleware('permission:settings.update.manage')
        ->prefix('settings/update')
        ->name('settings.update.')
        ->group(function () {
            Route::get('/', [UpdateSettingController::class, 'index'])->name('index');
            Route::get('/progress', [UpdateSettingController::class, 'progress'])->name('progress');
            Route::post('/check', [UpdateSettingController::class, 'check'])->name('check');
            Route::post('/install', [UpdateSettingController::class, 'install'])->name('install');
        });
});

require __DIR__ . '/ajax.php';
require __DIR__ . '/auth.php';
