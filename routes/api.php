<?php

use App\Http\Controllers\Api\AbsensiApiController;
use App\Http\Controllers\Api\AppSettingApiController;
use App\Http\Controllers\Api\KelasApiController;
use App\Http\Controllers\Api\SiswaApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('absensi')->group(function () {
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

Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/kelas', [KelasApiController::class, 'index']);
    Route::post('/kelas', [KelasApiController::class, 'store']);
    Route::patch('/kelas/{kelas}', [KelasApiController::class, 'update']);
    Route::delete('/kelas/{kelas}', [KelasApiController::class, 'destroy']);

    Route::post('/siswa/import', [SiswaApiController::class, 'import']);
    Route::get('/siswa', [SiswaApiController::class, 'index']);
    Route::post('/siswa', [SiswaApiController::class, 'store']);
    Route::get('/siswa/{siswa}', [SiswaApiController::class, 'show']);
    Route::patch('/siswa/{siswa}', [SiswaApiController::class, 'update']);
    Route::delete('/siswa/{siswa}', [SiswaApiController::class, 'destroy']);
    Route::post('/siswa/{siswa}/foto-referensi', [SiswaApiController::class, 'fotoReferensi']);

    Route::get('/settings/school', [AppSettingApiController::class, 'show']);
    Route::post('/settings/school', [AppSettingApiController::class, 'update']);
});
