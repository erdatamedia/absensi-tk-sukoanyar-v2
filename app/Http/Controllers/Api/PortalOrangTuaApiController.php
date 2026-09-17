<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use Illuminate\Http\Request;

class PortalOrangTuaApiController extends Controller
{
    public function absensiAnak(Request $request)
    {
        $anakIds = $request->user()->relasiAnak()->pluck('siswa_id');

        $tanggalDari = $request->input('tanggal_dari');
        $tanggalSampai = $request->input('tanggal_sampai');

        $absensi = Absensi::with(['siswa.kelas'])
            ->whereIn('siswa_id', $anakIds)
            ->when($tanggalDari, function ($query, $tanggalDari) {
                $query->whereDate('tanggal', '>=', $tanggalDari);
            })
            ->when($tanggalSampai, function ($query, $tanggalSampai) {
                $query->whereDate('tanggal', '<=', $tanggalSampai);
            })
            ->orderByDesc('tanggal')
            ->orderByDesc('jam_masuk')
            ->paginate(20)
            ->withQueryString();

        return response()->json([
            'status' => 'ok',
            'absensi' => $absensi,
        ]);
    }
}
