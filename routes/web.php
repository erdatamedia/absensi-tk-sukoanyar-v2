<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\SiswaController;

use App\Http\Controllers\AbsensiController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KelasController;
use App\Http\Controllers\AppSettingController;
use App\Http\Controllers\Api\ParentAuthApiController;

Route::post('/parent-login', [ParentAuthApiController::class, 'login']);

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/absensi', [AbsensiController::class,'index']);
    Route::get('/absensi/manual', [AbsensiController::class,'manualForm']);
    Route::post('/absensi/manual', [AbsensiController::class,'manualStore']);
    Route::get('/absensi/monitor', [AbsensiController::class,'monitor']);
    Route::get('/absensi/monitor/data', [AbsensiController::class,'monitorData']);
    Route::get('/absensi/rekap', [AbsensiController::class,'rekapSederhana']);
    Route::get('/absensi/rekap/export', [AbsensiController::class,'exportRekap'])->name('absensi.rekap.export');
    Route::get('/absensi/rekap/laporan-kelas', [AbsensiController::class,'classReportPdf'])->name('absensi.rekap.class-report');
    Route::get('/absensi/riwayat', [AbsensiController::class,'riwayat']);
    Route::get('/absensi/riwayat/export', [AbsensiController::class,'exportRiwayat']);
    Route::post('/absensi/mark-alpha', [AbsensiController::class,'markAlpha']);
    Route::post('/absensi/unmark-alpha', [AbsensiController::class,'unmarkAlpha']);
    Route::post('/absensi/{absensi}/status', [AbsensiController::class,'updateStatus']);
    Route::post('/absensi/{absensi}/waktu', [AbsensiController::class,'updateWaktu']);
    Route::delete('/absensi/{absensi}', [AbsensiController::class,'destroy']);
    Route::post('/absensi/scan',[AbsensiController::class,'scan']);
    Route::post('/absensi/simpan',[AbsensiController::class,'simpan']);

    Route::post('/siswa/import', [SiswaController::class, 'import'])->name('siswa.import');
    Route::get('/siswa/kartu-pdf/massal', [SiswaController::class, 'massCardPdf'])->name('siswa.cards-pdf');
    Route::get('/siswa/{siswa}/kartu-pdf', [SiswaController::class, 'cardPdf'])->name('siswa.card-pdf');
    Route::resource('siswa', SiswaController::class);
    Route::get('/kelas', [KelasController::class, 'index']);
    Route::post('/kelas', [KelasController::class, 'store']);
    Route::patch('/kelas/{kelas}', [KelasController::class, 'update']);
    Route::delete('/kelas/{kelas}', [KelasController::class, 'destroy']);
    Route::get('/pengaturan-sekolah', [AppSettingController::class, 'edit'])->name('settings.school.edit');
    Route::put('/pengaturan-sekolah', [AppSettingController::class, 'update'])->name('settings.school.update');
});

Route::view('/', 'welcome');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('/dashboard/data', [DashboardController::class, 'data'])
    ->middleware(['auth', 'verified', 'role:admin']);

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
