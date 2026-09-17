<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use Illuminate\Http\Request;

class KelasApiController extends Controller
{
    public function index()
    {
        return response()->json([
            'status' => 'ok',
            'kelas_list' => Kelas::withCount('siswa')->orderBy('nama_kelas')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_kelas' => ['required', 'string', 'max:100'],
            'tahun_ajaran' => ['required', 'string', 'max:20'],
        ]);

        $kelas = Kelas::create($validated);

        return response()->json(['status' => 'ok', 'kelas' => $kelas], 201);
    }

    public function update(Request $request, Kelas $kelas)
    {
        $validated = $request->validate([
            'nama_kelas' => ['required', 'string', 'max:100'],
            'tahun_ajaran' => ['required', 'string', 'max:20'],
        ]);

        $kelas->update($validated);

        return response()->json(['status' => 'ok', 'kelas' => $kelas]);
    }

    public function destroy(Kelas $kelas)
    {
        if ($kelas->siswa()->count() > 0) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Kelas tidak bisa dihapus karena masih memiliki data siswa.',
            ], 409);
        }

        $kelas->delete();

        return response()->json(['status' => 'ok']);
    }
}
