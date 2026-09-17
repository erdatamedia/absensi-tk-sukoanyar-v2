<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Services\FaceRecognitionClient;
use App\Services\SiswaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SiswaApiController extends Controller
{
    public function __construct(
        private SiswaService $siswaService,
        private FaceRecognitionClient $faceRecognitionClient
    ) {
    }

    public function index(Request $request)
    {
        $kelasId = $request->input('kelas_id');
        $q = trim((string) $request->input('q', ''));

        $siswa = Siswa::with('kelas')
            ->when($kelasId, function ($query, $kelasId) {
                $query->where('kelas_id', $kelasId);
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('nama', 'like', '%' . $q . '%')
                        ->orWhere('nis', 'like', '%' . $q . '%');
                });
            })
            ->orderBy('nama')
            ->get();

        return response()->json([
            'status' => 'ok',
            'siswa_list' => $siswa,
            'kelas_list' => Kelas::orderBy('nama_kelas')->get(),
        ]);
    }

    public function show(Siswa $siswa)
    {
        $siswa->load('kelas');

        return response()->json(['status' => 'ok', 'siswa' => $siswa]);
    }

    public function store(Request $request)
    {
        $validated = $this->siswaService->validate($request);

        $siswa = Siswa::create($validated);

        return response()->json(['status' => 'ok', 'siswa' => $siswa->load('kelas')], 201);
    }

    public function update(Request $request, Siswa $siswa)
    {
        $validated = $this->siswaService->validate($request, $siswa);

        $siswa->update($validated);

        return response()->json(['status' => 'ok', 'siswa' => $siswa->load('kelas')]);
    }

    public function destroy(Siswa $siswa)
    {
        $siswa->delete();

        return response()->json(['status' => 'ok']);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        $result = $this->siswaService->import($request->file('file'));

        if (isset($result['error'])) {
            return response()->json(['status' => 'error', 'msg' => $result['error']], 422);
        }

        return response()->json(['status' => 'ok'] + $result);
    }

    public function fotoReferensi(Request $request, Siswa $siswa)
    {
        $request->validate([
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
        ]);

        $file = $request->file('foto');
        $embedResult = $this->faceRecognitionClient->embed(base64_encode($file->get()));

        if ($embedResult === null) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Wajah tidak terdeteksi pada foto, atau layanan pengenalan wajah sedang tidak aktif. Coba foto lain dengan wajah yang jelas.',
            ], 422);
        }

        $oldFoto = $siswa->foto_referensi;

        $path = $file->store('referensi-wajah', 'public');

        $siswa->update([
            'foto_referensi' => $path,
            'face_embedding' => $embedResult['embedding'],
        ]);

        if ($oldFoto) {
            Storage::disk('public')->delete($oldFoto);
        }

        return response()->json([
            'status' => 'ok',
            'siswa' => $siswa->load('kelas'),
        ]);
    }
}
