<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Services\AbsensiService;
use App\Services\FaceRecognitionClient;
use App\Support\ActivityLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AbsensiApiController extends Controller
{
    public function __construct(
        private AbsensiService $absensi,
        private FaceRecognitionClient $faceRecognitionClient
    ) {
    }

    public function manualForm(Request $request)
    {
        $kelasId = $request->input('kelas_id');

        return response()->json([
            'cutoff_masuk' => $this->absensi->getMasukCutoff(),
            'kelas_list' => Kelas::orderBy('nama_kelas')->get(),
            'siswa_list' => Siswa::with('kelas')
                ->when($kelasId, function ($query, $kelasId) {
                    $query->where('kelas_id', $kelasId);
                })
                ->orderBy('nama')
                ->get(),
        ]);
    }

    public function manualStore(Request $request)
    {
        $validated = $request->validate([
            'tanggal' => ['required', 'date'],
            'siswa_id' => ['required', 'integer', 'exists:siswa,id'],
            'jenis' => ['required', 'in:masuk,pulang'],
            'jam' => ['nullable', 'date_format:H:i'],
            'status' => ['nullable', 'in:hadir,izin,sakit,alpha'],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'override_cutoff' => ['nullable', 'boolean'],
        ]);

        $tanggal = $validated['tanggal'];
        $siswaId = (int) $validated['siswa_id'];
        $jenis = $validated['jenis'];
        $jam = ($validated['jam'] ?? now()->format('H:i')) . ':00';
        $status = $validated['status'] ?? 'hadir';
        $keterangan = trim((string) ($validated['keterangan'] ?? ''));
        if ($keterangan === '') {
            $keterangan = null;
        }
        $overrideCutoff = (bool) ($validated['override_cutoff'] ?? false);
        $timing = $this->absensi->evaluateMasukTiming($jam);
        $isTerlambat = $timing['is_late'];

        $absensi = Absensi::where('siswa_id', $siswaId)
            ->whereDate('tanggal', $tanggal)
            ->first();

        if ($jenis === 'masuk') {
            if (!$overrideCutoff && $timing['is_late']) {
                return response()->json([
                    'status' => 'error',
                    'msg' => "Jam masuk berada di luar jam operasional {$timing['start']} - {$timing['end']}. Centang override jika memang perlu.",
                ], 422);
            }

            if ($absensi) {
                return response()->json([
                    'status' => 'error',
                    'msg' => 'Absensi masuk untuk siswa ini pada tanggal tersebut sudah ada.',
                ], 409);
            }

            $createdAbsensi = Absensi::create([
                'siswa_id' => $siswaId,
                'tanggal' => $tanggal,
                'jam_masuk' => $jam,
                'status' => $status,
                'keterangan' => $keterangan,
                'sumber' => 'manual',
                'terlambat' => $isTerlambat,
            ]);

            ActivityLogger::log(
                'absensi.manual_masuk',
                'Absensi masuk manual disimpan.',
                $createdAbsensi,
                [
                    'siswa_id' => $siswaId,
                    'tanggal' => $tanggal,
                    'jam' => $jam,
                    'status' => $status,
                ]
            );

            return response()->json(['status' => 'ok', 'absensi' => $createdAbsensi]);
        }

        if (!$absensi) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Belum ada absensi masuk untuk siswa ini pada tanggal tersebut.',
            ], 409);
        }

        if ($absensi->jam_pulang !== null) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Absensi pulang untuk siswa ini sudah tercatat.',
            ], 409);
        }

        $absensi->update([
            'jam_pulang' => $jam,
            'keterangan' => $keterangan ?? $absensi->keterangan,
        ]);

        ActivityLogger::log(
            'absensi.manual_pulang',
            'Absensi pulang manual disimpan.',
            $absensi,
            [
                'siswa_id' => $siswaId,
                'tanggal' => $tanggal,
                'jam' => $jam,
            ]
        );

        return response()->json(['status' => 'ok', 'absensi' => $absensi]);
    }

    public function monitor(Request $request)
    {
        $tanggal = $request->input('tanggal', now()->toDateString());
        $monitorData = $this->absensi->getMonitorData($tanggal, 25);

        return response()->json([
            'status' => 'ok',
            'tanggal' => $tanggal,
            'summary' => $monitorData['summary'],
            'aktivitas' => $this->formatAktivitas($monitorData['aktivitas']),
        ]);
    }

    public function monitorData(Request $request)
    {
        $tanggal = $request->input('tanggal', now()->toDateString());
        $limit = (int) $request->input('limit', 25);
        if ($limit <= 0) {
            $limit = 25;
        }
        if ($limit > 100) {
            $limit = 100;
        }

        $monitorData = $this->absensi->getMonitorData($tanggal, $limit);

        return response()->json([
            'status' => 'ok',
            'tanggal' => $tanggal,
            'summary' => $monitorData['summary'],
            'aktivitas' => $this->formatAktivitas($monitorData['aktivitas']),
            'server_time' => now()->toDateTimeString(),
        ]);
    }

    private function formatAktivitas($aktivitas)
    {
        return $aktivitas->map(function ($item) {
            return [
                'id' => $item->id,
                'updated_at' => (string) $item->updated_at,
                'nis' => $item->siswa->nis ?? '-',
                'nama' => $item->siswa->nama ?? '-',
                'kelas' => $item->siswa->kelas->nama_kelas ?? '-',
                'jam_masuk' => $item->jam_masuk ?? '-',
                'jam_pulang' => $item->jam_pulang ?? '-',
                'status_absensi' => $item->status,
                'keterangan' => $item->keterangan ?? '-',
                'sumber' => $item->sumber ?? '-',
                'terlambat' => (bool) $item->terlambat,
            ];
        })->values();
    }

    public function riwayat(Request $request)
    {
        $tanggal = $request->input('tanggal', now()->toDateString());
        $kelasId = $request->input('kelas_id');
        $q = trim((string) $request->input('q', ''));
        $statusFilter = $request->input('status_filter');
        $sumberFilter = $request->input('sumber_filter');
        $terlambatFilter = $request->input('terlambat_filter');
        $perPage = (int) $request->input('per_page', 20);
        if ($perPage <= 0) {
            $perPage = 20;
        }

        $query = $this->absensi->buildRiwayatQuery(
            $tanggal,
            $kelasId,
            $q,
            $statusFilter,
            $sumberFilter,
            $terlambatFilter
        );

        $riwayat = (clone $query)
            ->orderBy('jam_masuk')
            ->paginate($perPage)
            ->withQueryString();

        $summary = [
            'total' => (clone $query)->count(),
            'sudah_pulang' => (clone $query)->whereNotNull('jam_pulang')->count(),
            'belum_pulang' => (clone $query)->whereNull('jam_pulang')->count(),
        ];

        $kelasList = Kelas::orderBy('nama_kelas')->get();

        $siswaSudahMasukIds = Absensi::whereDate('tanggal', $tanggal)
            ->when($kelasId, function ($query, $kelasId) {
                $query->whereHas('siswa', function ($siswaQuery) use ($kelasId) {
                    $siswaQuery->where('kelas_id', $kelasId);
                });
            })
            ->pluck('siswa_id');

        $siswaBelumMasuk = Siswa::with('kelas')
            ->when($kelasId, function ($query, $kelasId) {
                $query->where('kelas_id', $kelasId);
            })
            ->whereNotIn('id', $siswaSudahMasukIds)
            ->orderBy('nama')
            ->get();

        $siswaBelumPulang = Siswa::with('kelas')
            ->whereIn('id', function ($subQuery) use ($tanggal, $kelasId) {
                $subQuery->select('siswa_id')
                    ->from('absensi')
                    ->whereDate('tanggal', $tanggal)
                    ->whereNull('jam_pulang');
            })
            ->when($kelasId, function ($query, $kelasId) {
                $query->where('kelas_id', $kelasId);
            })
            ->orderBy('nama')
            ->get();

        return response()->json([
            'status' => 'ok',
            'riwayat' => $riwayat,
            'summary' => $summary,
            'kelas_list' => $kelasList,
            'siswa_belum_masuk' => $siswaBelumMasuk,
            'siswa_belum_pulang' => $siswaBelumPulang,
        ]);
    }

    public function rekap(Request $request)
    {
        $periodMeta = $this->absensi->resolveReportPeriod($request);
        $kelasId = $request->input('kelas_id');

        $classReports = $this->absensi->buildDailyClassReports(
            $periodMeta['start_date'],
            $periodMeta['end_date'],
            $kelasId,
            $periodMeta['is_single_day']
        );

        $summary = $this->absensi->buildDailySummaryFromReports($classReports);
        $trend = $this->absensi->buildTrendData($periodMeta['start_date'], $periodMeta['end_date'], $kelasId);

        return response()->json([
            'status' => 'ok',
            'period_meta' => $periodMeta,
            'kelas_list' => Kelas::orderBy('nama_kelas')->get(),
            'class_reports' => $classReports,
            'summary' => $summary,
            'trend' => $trend,
        ]);
    }

    public function updateStatus(Request $request, Absensi $absensi)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:hadir,izin,sakit,alpha'],
        ]);

        $absensi->update(['status' => $validated['status']]);

        ActivityLogger::log(
            'absensi.update_status',
            'Status absensi diperbarui.',
            $absensi,
            ['status' => $validated['status']]
        );

        return response()->json(['status' => 'ok', 'absensi' => $absensi]);
    }

    public function updateWaktu(Request $request, Absensi $absensi)
    {
        $validated = $request->validate([
            'jam_masuk' => ['nullable', 'date_format:H:i'],
            'jam_pulang' => ['nullable', 'date_format:H:i'],
        ]);

        $jamMasuk = $validated['jam_masuk'] ?? null;
        $jamPulang = $validated['jam_pulang'] ?? null;

        if ($jamPulang !== null && $jamMasuk === null) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Jam pulang tidak bisa diisi jika jam masuk kosong.',
            ], 422);
        }

        if ($jamMasuk !== null && $jamPulang !== null && $jamPulang < $jamMasuk) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Jam pulang tidak boleh lebih kecil dari jam masuk.',
            ], 422);
        }

        $absensi->update([
            'jam_masuk' => $jamMasuk ? $jamMasuk . ':00' : null,
            'jam_pulang' => $jamPulang ? $jamPulang . ':00' : null,
        ]);

        ActivityLogger::log(
            'absensi.update_waktu',
            'Jam masuk/pulang absensi diperbarui.',
            $absensi,
            ['jam_masuk' => $jamMasuk, 'jam_pulang' => $jamPulang]
        );

        return response()->json(['status' => 'ok', 'absensi' => $absensi]);
    }

    public function destroy(Absensi $absensi)
    {
        $fotoMasuk = $absensi->foto_masuk;
        $fotoPulang = $absensi->foto_pulang;
        $properties = [
            'absensi_id' => $absensi->id,
            'siswa_id' => $absensi->siswa_id,
            'tanggal' => $absensi->tanggal,
        ];
        $absensi->delete();
        $this->absensi->deleteDatasetFile($fotoMasuk);
        $this->absensi->deleteDatasetFile($fotoPulang);

        ActivityLogger::log('absensi.destroy', 'Data absensi dihapus.', null, $properties);

        return response()->json(['status' => 'ok']);
    }

    public function markAlpha(Request $request)
    {
        $validated = $request->validate([
            'tanggal' => ['required', 'date'],
            'kelas_id' => ['nullable'],
            'q' => ['nullable', 'string'],
            'status_filter' => ['nullable', 'in:hadir,izin,sakit,alpha'],
            'sumber_filter' => ['nullable', 'in:scan_qr,manual,auto_alpha'],
            'terlambat_filter' => ['nullable', 'in:ya,tidak'],
        ]);

        $tanggal = $validated['tanggal'];
        $kelasId = $validated['kelas_id'] ?? null;
        $q = trim((string) ($validated['q'] ?? ''));
        $statusFilter = $validated['status_filter'] ?? null;
        $sumberFilter = $validated['sumber_filter'] ?? null;
        $terlambatFilter = $validated['terlambat_filter'] ?? null;

        if ($this->absensi->markAlphaWouldBeExcludedByFilters($statusFilter, $sumberFilter, $terlambatFilter)) {
            return response()->json([
                'status' => 'ok',
                'total_ditandai' => 0,
                'msg' => 'Tidak ada data alpha otomatis yang cocok dengan filter aktif.',
            ]);
        }

        $siswaQuery = Siswa::query()
            ->when($kelasId, function ($query, $kelasId) {
                $query->where('kelas_id', $kelasId);
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('nama', 'like', '%' . $q . '%')
                        ->orWhere('nis', 'like', '%' . $q . '%');
                });
            });

        $sudahAdaAbsensiIds = Absensi::whereDate('tanggal', $tanggal)
            ->when($kelasId, function ($query, $kelasId) {
                $query->whereHas('siswa', function ($siswaQuery) use ($kelasId) {
                    $siswaQuery->where('kelas_id', $kelasId);
                });
            })
            ->pluck('siswa_id');

        $siswaBelumAbsenIds = (clone $siswaQuery)
            ->whereNotIn('id', $sudahAdaAbsensiIds)
            ->pluck('id');

        $totalDitandai = 0;

        foreach ($siswaBelumAbsenIds as $siswaId) {
            $alpha = Absensi::create([
                'siswa_id' => $siswaId,
                'tanggal' => $tanggal,
                'status' => 'alpha',
                'sumber' => 'auto_alpha',
                'terlambat' => false,
            ]);

            ActivityLogger::log(
                'absensi.auto_alpha_mark',
                'Siswa ditandai alpha otomatis.',
                $alpha,
                ['siswa_id' => $siswaId, 'tanggal' => $tanggal]
            );

            $totalDitandai++;
        }

        return response()->json(['status' => 'ok', 'total_ditandai' => $totalDitandai]);
    }

    public function unmarkAlpha(Request $request)
    {
        $validated = $request->validate([
            'tanggal' => ['required', 'date'],
            'kelas_id' => ['nullable'],
            'q' => ['nullable', 'string'],
            'status_filter' => ['nullable', 'in:hadir,izin,sakit,alpha'],
            'sumber_filter' => ['nullable', 'in:scan_qr,manual,auto_alpha'],
            'terlambat_filter' => ['nullable', 'in:ya,tidak'],
        ]);

        $query = $this->absensi->buildRiwayatQuery(
            $validated['tanggal'],
            $validated['kelas_id'] ?? null,
            trim((string) ($validated['q'] ?? '')),
            $validated['status_filter'] ?? null,
            $validated['sumber_filter'] ?? null,
            $validated['terlambat_filter'] ?? null
        )
            ->where('status', 'alpha')
            ->whereNull('jam_masuk')
            ->whereNull('jam_pulang')
            ->whereNull('foto_masuk')
            ->whereNull('foto_pulang');

        $items = (clone $query)->get();
        $totalDibatalkan = $items->count();
        $query->delete();

        foreach ($items as $item) {
            ActivityLogger::log(
                'absensi.auto_alpha_unmark',
                'Alpha otomatis dibatalkan.',
                null,
                [
                    'absensi_id' => $item->id,
                    'siswa_id' => $item->siswa_id,
                    'tanggal' => $item->tanggal,
                ]
            );
        }

        return response()->json(['status' => 'ok', 'total_dibatalkan' => $totalDibatalkan]);
    }

    public function scan(Request $request)
    {
        $request->validate([
            'qr_token' => ['required', 'string'],
        ]);

        $siswa = Siswa::where('qr_token', $request->qr_token)->first();

        if (!$siswa) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Siswa tidak ditemukan',
            ]);
        }

        return response()->json($this->buildScanStatusResponse($siswa));
    }

    public function recognizeFace(Request $request)
    {
        $request->validate([
            'foto' => ['required', 'string'],
        ]);

        $candidates = Siswa::query()
            ->whereNotNull('face_embedding')
            ->get(['id', 'face_embedding'])
            ->map(fn (Siswa $siswa) => ['id' => $siswa->id, 'embedding' => $siswa->face_embedding]);

        if ($candidates->isEmpty()) {
            return response()->json([
                'status' => 'no_match',
                'msg' => 'Belum ada siswa yang terdaftar wajahnya.',
            ]);
        }

        $result = $this->faceRecognitionClient->recognize($request->foto, $candidates);

        if ($result === null) {
            return response()->json([
                'status' => 'service_unavailable',
                'msg' => 'Layanan pengenalan wajah sedang tidak aktif. Gunakan scan QR.',
            ]);
        }

        if (empty($result['siswa_id'])) {
            return response()->json([
                'status' => 'no_match',
                'score' => $result['score'] ?? null,
            ]);
        }

        $siswa = Siswa::find($result['siswa_id']);

        if (!$siswa) {
            return response()->json([
                'status' => 'no_match',
            ]);
        }

        return response()->json(
            $this->buildScanStatusResponse($siswa) + ['score' => $result['score']]
        );
    }

    private function buildScanStatusResponse(Siswa $siswa): array
    {
        $today = now()->toDateString();
        $absensiHariIni = Absensi::where('siswa_id', $siswa->id)
            ->whereDate('tanggal', $today)
            ->first();

        $canMasuk = $absensiHariIni === null;
        $canPulang = $absensiHariIni !== null && $absensiHariIni->jam_pulang === null;
        $alreadyComplete = $absensiHariIni !== null && $absensiHariIni->jam_pulang !== null;

        return [
            'status' => 'ok',
            'siswa_id' => $siswa->id,
            'nama' => $siswa->nama,
            'can_masuk' => $canMasuk,
            'can_pulang' => $canPulang,
            'already_complete' => $alreadyComplete,
            'jam_masuk' => $absensiHariIni?->jam_masuk,
            'jam_pulang' => $absensiHariIni?->jam_pulang,
        ];
    }

    public function simpan(Request $request)
    {
        $request->validate([
            'siswa_id' => ['required', 'integer', 'exists:siswa,id'],
            'foto' => ['required', 'string'],
            'jenis' => ['required', 'in:masuk,pulang'],
        ]);

        $siswa = Siswa::find($request->siswa_id);

        if (!$siswa) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Siswa tidak ditemukan',
            ]);
        }

        $today = now()->toDateString();
        $absensiHariIni = Absensi::where('siswa_id', $siswa->id)
            ->whereDate('tanggal', $today)
            ->first();

        $jenis = $request->jenis;

        if ($jenis === 'masuk' && $absensiHariIni) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Absensi masuk hari ini sudah tercatat',
            ], 409);
        }

        if ($jenis === 'pulang' && !$absensiHariIni) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Belum ada absensi masuk hari ini',
            ], 409);
        }

        if ($jenis === 'pulang' && $absensiHariIni->jam_pulang !== null) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Absensi pulang hari ini sudah tercatat',
            ], 409);
        }

        $fotoBase64 = $request->foto;
        $base64Body = $fotoBase64;

        if (preg_match('/^data:image\/(jpeg|jpg|png);base64,(.+)$/', $fotoBase64, $matches)) {
            $base64Body = $matches[2];
        }

        $base64Body = str_replace(' ', '+', $base64Body);
        $imageBinary = base64_decode($base64Body, true);

        if ($imageBinary === false) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Format foto tidak valid',
            ], 422);
        }

        if (strlen($imageBinary) > 5 * 1024 * 1024) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Ukuran foto terlalu besar (maksimal 5MB)',
            ], 422);
        }

        $imageInfo = @getimagesizefromstring($imageBinary);
        if ($imageInfo === false || !in_array($imageInfo['mime'] ?? '', ['image/jpeg', 'image/png'], true)) {
            return response()->json([
                'status' => 'error',
                'msg' => 'File foto harus berupa JPEG atau PNG yang valid',
            ], 422);
        }

        $normalizedImage = $this->absensi->normalizeDatasetImage($imageBinary, $imageInfo);

        if ($normalizedImage !== null) {
            $imageBinary = $normalizedImage;
        }

        $filename = 'siswa_' . $siswa->id . '_' . $jenis . '_' . now()->format('YmdHis') . '_' . Str::lower(Str::random(6)) . '.jpg';
        $relativePath = 'dataset/' . $filename;

        try {
            Storage::disk('public')->makeDirectory('dataset');
            $written = Storage::disk('public')->put($relativePath, $imageBinary);

            if ($written === false) {
                throw new \RuntimeException('Filesystem public menolak operasi put untuk dataset.');
            }
        } catch (\Throwable $e) {
            Log::error('Gagal menyimpan foto absensi ke storage public.', [
                'siswa_id' => $siswa->id,
                'jenis' => $jenis,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'msg' => 'Foto gagal disimpan di server. Periksa permission storage dan symlink public/storage.',
            ], 500);
        }

        if ($jenis === 'masuk') {
            try {
                $timing = $this->absensi->evaluateMasukTiming(now()->toTimeString());
                $isTerlambat = $timing['is_late'];

                $createdAbsensi = Absensi::create([
                    'siswa_id' => $siswa->id,
                    'tanggal' => $today,
                    'jam_masuk' => now()->toTimeString(),
                    'foto_masuk' => $filename,
                    'status' => 'hadir',
                    'sumber' => 'scan_qr',
                    'terlambat' => $isTerlambat,
                ]);

                ActivityLogger::log(
                    'absensi.scan_masuk',
                    "Absensi masuk dari scanner disimpan untuk {$siswa->nama}.",
                    $createdAbsensi,
                    ['siswa_id' => $siswa->id, 'tanggal' => $today, 'terlambat' => $isTerlambat]
                );
            } catch (QueryException $e) {
                if ((string) $e->getCode() === '23000') {
                    Storage::disk('public')->delete($relativePath);

                    return response()->json([
                        'status' => 'error',
                        'msg' => 'Absensi masuk hari ini sudah tercatat',
                    ], 409);
                }

                Storage::disk('public')->delete($relativePath);
                throw $e;
            }
        } else {
            try {
                $oldFotoPulang = $absensiHariIni->foto_pulang;
                $absensiHariIni->update([
                    'jam_pulang' => now()->toTimeString(),
                    'foto_pulang' => $filename,
                ]);
                if ($oldFotoPulang && $oldFotoPulang !== $filename) {
                    $this->absensi->deleteDatasetFile($oldFotoPulang);
                }

                ActivityLogger::log(
                    'absensi.scan_pulang',
                    "Absensi pulang dari scanner disimpan untuk {$siswa->nama}.",
                    $absensiHariIni,
                    ['siswa_id' => $siswa->id, 'tanggal' => $today]
                );
            } catch (\Throwable $e) {
                Storage::disk('public')->delete($relativePath);
                throw $e;
            }
        }

        return response()->json([
            'status' => 'ok',
            'nama' => $siswa->nama,
            'jenis' => $jenis,
        ]);
    }
}
