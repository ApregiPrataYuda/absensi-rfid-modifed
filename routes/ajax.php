<?php

use App\Http\Controllers\AbsensiPelajaranController;
use App\Http\Controllers\DataGuruController;
use App\Http\Controllers\DataPiketController;
use App\Http\Controllers\DataSiswaController;
use App\Http\Controllers\KelolaAbsenController;
use App\Http\Controllers\KelolaKelasController;
use App\Http\Controllers\KenaikanKelasController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\RekapAbsensiController;
use App\Http\Controllers\RekapAbsensiPelajaranController;
use App\Http\Controllers\RekapBulananController;
use App\Http\Controllers\ScannerController;
use Illuminate\Support\Facades\Route;

Route::get('/ajax/reports/export', [RekapAbsensiController::class, 'download'])
    ->name('ajax.reports.download');

Route::middleware('auth')
    ->prefix('ajax')
    ->name('ajax.')
    ->group(function (): void {
        Route::prefix('shared')
            ->name('shared.')
            ->group(function (): void {
                Route::post('/classes', [KelolaKelasController::class, 'classOptions'])->name('classes.index');
                Route::post('/config', [KelolaAbsenController::class, 'appConfig'])->name('config.show');
                Route::post('/config/save', [KelolaAbsenController::class, 'updateAppConfig'])->name('config.update');
                Route::post('/holidays', [KelolaAbsenController::class, 'holidayList'])->name('holidays.index');
                Route::post('/holidays/store', [KelolaAbsenController::class, 'addHoliday'])->name('holidays.store');
                Route::post('/holidays/destroy', [KelolaAbsenController::class, 'deleteHoliday'])->name('holidays.destroy');
                Route::post('/holidays/import', [KelolaAbsenController::class, 'importHoliday'])->name('holidays.import');
            });

        Route::prefix('students')
            ->name('students.')
            ->controller(DataSiswaController::class)
            ->group(function (): void {
                Route::post('/', 'index')->name('index');
                Route::post('/store', 'store')->name('store');
                Route::post('/update', 'update')->name('update');
                Route::post('/destroy', 'destroy')->name('destroy');
                Route::post('/import', 'import')->name('import');
                Route::post('/by-class', 'byClass')->name('by-class');
                Route::post('/lookup-scan', 'lookupForScan')->name('lookup-scan');
            });

        Route::prefix('staff')
            ->name('staff.')
            ->group(function (): void {
                Route::post('/guru', [DataGuruController::class, 'index'])->name('guru.index');
                Route::post('/guru/store', [DataGuruController::class, 'store'])->name('guru.store');
                Route::post('/guru/update', [DataGuruController::class, 'update'])->name('guru.update');
                Route::post('/guru/destroy', [DataGuruController::class, 'destroy'])->name('guru.destroy');
                Route::post('/guru/import', [DataGuruController::class, 'import'])->name('guru.import');
                Route::post('/piket', [DataPiketController::class, 'index'])->name('piket.index');
                Route::post('/piket/store', [DataPiketController::class, 'store'])->name('piket.store');
                Route::post('/piket/update', [DataPiketController::class, 'update'])->name('piket.update');
                Route::post('/piket/destroy', [DataPiketController::class, 'destroy'])->name('piket.destroy');
                Route::post('/piket/import', [DataPiketController::class, 'import'])->name('piket.import');
            });

        Route::prefix('attendance')
            ->name('attendance.')
            ->group(function (): void {
                Route::post('/monitoring', [MonitoringController::class, 'monitoring'])->name('monitoring');
                Route::post('/list', [RekapAbsensiController::class, 'attendanceList'])->name('list');
                Route::post('/monthly-report', [RekapBulananController::class, 'monthlyReport'])->name('monthly-report');
                Route::post('/batch-scan', [ScannerController::class, 'batchScan'])->name('batch-scan');
                Route::post('/scan-rfid', [ScannerController::class, 'scanRfid'])->name('scan-rfid');
                Route::post('/update-status', [ScannerController::class, 'updateStatus'])->name('update-status');
            });

        Route::prefix('reports')
            ->name('reports.')
            ->controller(RekapAbsensiController::class)
            ->group(function (): void {
                Route::post('/generate-excel', 'generateExcelAction')->name('generate-excel');
                Route::post('/template-excel', 'templateExcel')->name('template-excel');
                Route::post('/archive-preview', 'archivePreview')->name('archive-preview');
                Route::post('/archive-reset', 'archiveReset')->name('archive-reset');
            });

        Route::prefix('promotions')
            ->name('promotions.')
            ->controller(KenaikanKelasController::class)
            ->group(function (): void {
                Route::post('/grade', 'grade')->name('grade');
                Route::post('/individual', 'individual')->name('individual');
            });

        Route::prefix('lessons')
            ->name('lessons.')
            ->group(function (): void {
                Route::prefix('sessions')
                    ->name('sessions.')
                    ->controller(AbsensiPelajaranController::class)
                    ->group(function (): void {
                        Route::post('/today', 'todaySessions')->name('today');
                        Route::post('/detail', 'sessionDetail')->name('detail');
                        Route::post('/start', 'startSession')->name('start');
                        Route::post('/scan', 'scan')->name('scan');
                        Route::post('/set-status', 'setStatus')->name('set-status');
                        Route::post('/broadcast', 'broadcast')->name('broadcast');
                        Route::post('/close', 'closeSession')->name('close');
                    });

                Route::prefix('reports')
                    ->name('reports.')
                    ->controller(RekapAbsensiPelajaranController::class)
                    ->group(function (): void {
                        Route::post('/', 'lessonReport')->name('index');
                        Route::post('/detail', 'lessonReportDetail')->name('detail');
                    });
            });
    });
