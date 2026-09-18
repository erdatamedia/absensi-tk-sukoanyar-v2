<?php

use App\Http\Controllers\Api\AbsensiApiController;
use App\Http\Controllers\Api\AppSettingApiController;
use App\Http\Controllers\Api\KelasApiController;
use App\Http\Controllers\Api\PortalOrangTuaApiController;
use App\Http\Controllers\Api\SiswaApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Public: non-sensitive school name/logo, needed to brand the login page
// and browser tab favicon before anyone is signed in.
Route::get('/settings/branding', [AppSettingApiController::class, 'branding']);

Route::middleware(['auth:sanctum', 'role:orang_tua'])->prefix('portal-ortu')->group(function () {
    Route::get('/absensi-anak', [PortalOrangTuaApiController::class, 'absensiAnak']);
});

// Absensi: guru mengoperasikan kiosk sehari-hari, sama seperti admin.
Route::middleware(['auth:sanctum', 'role:admin,guru'])->prefix('absensi')->group(function () {
    Route::get('/manual', [AbsensiApiController::class, 'manualForm']);
    Route::post('/manual', [AbsensiApiController::class, 'manualStore']);
    Route::get('/monitor', [AbsensiApiController::class, 'monitor']);
    Route::get('/monitor/data', [AbsensiApiController::class, 'monitorData']);
    Route::get('/riwayat', [AbsensiApiController::class, 'riwayat']);
    Route::get('/rekap', [AbsensiApiController::class, 'rekap']);
    Route::post('/mark-alpha', [AbsensiApiController::class, 'markAlpha']);
    Route::post('/unmark-alpha', [AbsensiApiController::class, 'unmarkAlpha']);
    Route::post('/scan', [AbsensiApiController::class, 'scan']);
    Route::post('/recognize-face', [AbsensiApiController::class, 'recognizeFace']);
    Route::post('/simpan', [AbsensiApiController::class, 'simpan']);
    Route::post('/{absensi}/status', [AbsensiApiController::class, 'updateStatus']);
    Route::post('/{absensi}/waktu', [AbsensiApiController::class, 'updateWaktu']);
    Route::delete('/{absensi}', [AbsensiApiController::class, 'destroy']);
});

// Data Siswa: guru hanya boleh melihat (read-only), sisanya khusus admin.
Route::middleware(['auth:sanctum', 'role:admin,guru'])->group(function () {
    Route::get('/siswa', [SiswaApiController::class, 'index']);
    Route::get('/siswa/{siswa}', [SiswaApiController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/kelas', [KelasApiController::class, 'index']);
    Route::post('/kelas', [KelasApiController::class, 'store']);
    Route::patch('/kelas/{kelas}', [KelasApiController::class, 'update']);
    Route::delete('/kelas/{kelas}', [KelasApiController::class, 'destroy']);

    Route::post('/siswa/import', [SiswaApiController::class, 'import']);
    Route::post('/siswa', [SiswaApiController::class, 'store']);
    Route::patch('/siswa/{siswa}', [SiswaApiController::class, 'update']);
    Route::delete('/siswa/{siswa}', [SiswaApiController::class, 'destroy']);
    Route::post('/siswa/{siswa}/foto-referensi', [SiswaApiController::class, 'fotoReferensi']);

    Route::get('/settings/school', [AppSettingApiController::class, 'show']);
    Route::post('/settings/school', [AppSettingApiController::class, 'update']);
});
