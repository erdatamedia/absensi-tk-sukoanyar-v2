<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use App\Models\Siswa;
use App\Models\Absensi;
use App\Models\Kelas;
use App\Support\ActivityLogger;
use App\Support\Branding;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\AbsensiService;

class AbsensiController extends Controller
{
    public function __construct(private AbsensiService $absensi)
    {
    }

    public function index()
    {
        return view('absensi.scan');
    }

    public function manualForm(Request $request)
    {
        $tanggal = $request->input('tanggal', now()->toDateString());
        $kelasId = $request->input('kelas_id');
        $cutoffMasuk = $this->absensi->getMasukCutoff();

        $kelasList = Kelas::orderBy('nama_kelas')->get();
        $siswaList = Siswa::with('kelas')
            ->when($kelasId, function ($query, $kelasId) {
                $query->where('kelas_id', $kelasId);
            })
            ->orderBy('nama')
            ->get();

        return view('absensi.manual', [
            'tanggal' => $tanggal,
            'kelasId' => $kelasId,
            'kelasList' => $kelasList,
            'siswaList' => $siswaList,
            'cutoffMasuk' => $cutoffMasuk,
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
            'kelas_id' => ['nullable'],
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
                return redirect('/absensi/manual?' . http_build_query([
                    'tanggal' => $tanggal,
                    'kelas_id' => $validated['kelas_id'] ?? null,
                ]))->with('error', "Jam masuk berada di luar jam operasional {$timing['start']} - {$timing['end']}. Centang override jika memang perlu.");
            }

            if ($absensi) {
                return redirect('/absensi/manual?' . http_build_query([
                    'tanggal' => $tanggal,
                    'kelas_id' => $validated['kelas_id'] ?? null,
                ]))->with('error', 'Absensi masuk untuk siswa ini pada tanggal tersebut sudah ada.');
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

            return redirect('/absensi/manual?' . http_build_query([
                'tanggal' => $tanggal,
                'kelas_id' => $validated['kelas_id'] ?? null,
            ]))->with('success', 'Absensi masuk manual berhasil disimpan.');
        }

        if (!$absensi) {
            return redirect('/absensi/manual?' . http_build_query([
                'tanggal' => $tanggal,
                'kelas_id' => $validated['kelas_id'] ?? null,
            ]))->with('error', 'Belum ada absensi masuk untuk siswa ini pada tanggal tersebut.');
        }

        if ($absensi->jam_pulang !== null) {
            return redirect('/absensi/manual?' . http_build_query([
                'tanggal' => $tanggal,
                'kelas_id' => $validated['kelas_id'] ?? null,
            ]))->with('error', 'Absensi pulang untuk siswa ini sudah tercatat.');
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

        return redirect('/absensi/manual?' . http_build_query([
            'tanggal' => $tanggal,
            'kelas_id' => $validated['kelas_id'] ?? null,
        ]))->with('success', 'Absensi pulang manual berhasil disimpan.');
    }

    public function monitor()
    {
        $today = now()->toDateString();
        $monitorData = $this->absensi->getMonitorData($today, 25);

        return view('absensi.monitor', [
            'today' => $today,
            'summary' => $monitorData['summary'],
            'aktivitasTerbaru' => $monitorData['aktivitas'],
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
            'aktivitas' => $monitorData['aktivitas']->map(function ($item) {
                return [
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
            })->values(),
            'server_time' => now()->toDateTimeString(),
        ]);
    }

    public function rekapSederhana(Request $request)
    {
        $periodMeta = $this->absensi->resolveReportPeriod($request);
        $tanggal = $periodMeta['anchor_date'];
        $kelasId = $request->input('kelas_id');

        $kelasList = Kelas::orderBy('nama_kelas')->get();
        $classReports = $this->absensi->buildDailyClassReports(
            $periodMeta['start_date'],
            $periodMeta['end_date'],
            $kelasId,
            $periodMeta['is_single_day']
        );
        $hadirList = $classReports
            ->flatMap(fn ($report) => $report['rows'])
            ->filter(fn ($row) => $periodMeta['is_single_day']
                ? (($row['status'] ?? null) === 'hadir')
                : (($row['total_hadir'] ?? 0) > 0))
            ->sortBy([
                fn ($a, $b) => strcmp((string) ($a['last_jam_masuk'] ?? $a['jam_masuk'] ?? '99:99:99'), (string) ($b['last_jam_masuk'] ?? $b['jam_masuk'] ?? '99:99:99')),
                fn ($a, $b) => strcmp($a['nama'], $b['nama']),
            ])
            ->values();

        $summary = $this->absensi->buildDailySummaryFromReports($classReports);
        $trend = $this->absensi->buildTrendData($periodMeta['start_date'], $periodMeta['end_date'], $kelasId);

        return view('absensi.rekap-sederhana', [
            'tanggal' => $tanggal,
            'kelasId' => $kelasId,
            'kelasList' => $kelasList,
            'hadirList' => $hadirList,
            'classReports' => $classReports,
            'summary' => $summary,
            'periodMeta' => $periodMeta,
            'trend' => $trend,
        ]);
    }

    public function exportRekap(Request $request)
    {
        $periodMeta = $this->absensi->resolveReportPeriod($request);
        $tanggal = $periodMeta['anchor_date'];
        $kelasId = $request->input('kelas_id');
        $format = $request->input('format', 'csv');

        $classReports = $this->absensi->buildDailyClassReports(
            $periodMeta['start_date'],
            $periodMeta['end_date'],
            $kelasId,
            $periodMeta['is_single_day']
        );
        $selectedKelas = $kelasId ? Kelas::find($kelasId) : null;
        $kelasSlug = $selectedKelas?->nama_kelas ? Str::slug($selectedKelas->nama_kelas) : 'semua-kelas';
        $periodSlug = Str::slug($periodMeta['label']);

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('absensi.rekap-pdf', [
                'tanggal' => $tanggal,
                'selectedKelas' => $selectedKelas,
                'classReports' => $classReports,
                'summary' => $this->absensi->buildDailySummaryFromReports($classReports),
                'periodMeta' => $periodMeta,
                'schoolName' => Branding::schoolName(),
                'schoolTagline' => Branding::schoolTagline(),
                'logoPath' => Branding::logoPublicPath(),
                'generatedAt' => now(),
            ]);

            $pdf->setPaper('a4', 'portrait');

            return $pdf->stream("rekap-{$periodSlug}-{$kelasSlug}.pdf");
        }

        return response()->streamDownload(function () use ($classReports, $tanggal, $selectedKelas, $periodMeta) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Rekap Absensi']);
            fputcsv($handle, ['Sekolah', Branding::schoolName()]);
            fputcsv($handle, ['Periode', $periodMeta['label']]);
            fputcsv($handle, ['Rentang', $periodMeta['range_label']]);
            fputcsv($handle, ['Kelas', $selectedKelas?->nama_kelas ?? 'Semua Kelas']);
            fputcsv($handle, []);
            fputcsv($handle, [
                'Kelas',
                'NIS',
                'Nama',
                'Status',
                'Total Hadir',
                'Total Izin',
                'Total Sakit',
                'Total Alpha',
                'Total Terlambat',
                'Masuk Terakhir',
                'Pulang Terakhir',
                'Terlambat',
                'Sumber Terakhir',
                'Keterangan',
                'Foto Masuk',
                'Foto Pulang',
            ]);

            foreach ($classReports as $report) {
                foreach ($report['rows'] as $row) {
                    fputcsv($handle, [
                        $report['kelas']->nama_kelas,
                        $row['nis'],
                        $row['nama'],
                        $row['status_label'],
                        $row['total_hadir'] ?? 0,
                        $row['total_izin'] ?? 0,
                        $row['total_sakit'] ?? 0,
                        $row['total_alpha'] ?? 0,
                        $row['total_terlambat'] ?? 0,
                        $row['last_jam_masuk'] ?? $row['jam_masuk'] ?? '',
                        $row['last_jam_pulang'] ?? $row['jam_pulang'] ?? '',
                        $row['terlambat'] ? 'ya' : 'tidak',
                        $row['last_sumber'] ?? $row['sumber'] ?? '',
                        $row['keterangan'] ?? '',
                        $row['last_foto_masuk'] ?? $row['foto_masuk'] ?? '',
                        $row['last_foto_pulang'] ?? $row['foto_pulang'] ?? '',
                    ]);
                }
            }

            fclose($handle);
        }, "rekap-{$periodSlug}-{$kelasSlug}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function classReportPdf(Request $request)
    {
        $periodMeta = $this->absensi->resolveReportPeriod($request);
        $tanggal = $periodMeta['anchor_date'];
        $kelasId = $request->input('kelas_id');

        $classReports = $this->absensi->buildDailyClassReports(
            $periodMeta['start_date'],
            $periodMeta['end_date'],
            $kelasId,
            $periodMeta['is_single_day']
        );
        $selectedKelas = $kelasId ? Kelas::find($kelasId) : null;
        $kelasSlug = $selectedKelas?->nama_kelas ? Str::slug($selectedKelas->nama_kelas) : 'semua-kelas';

        $pdf = Pdf::loadView('absensi.class-report-pdf', [
            'tanggal' => $tanggal,
            'selectedKelas' => $selectedKelas,
            'classReports' => $classReports,
            'periodMeta' => $periodMeta,
            'schoolName' => Branding::schoolName(),
            'schoolTagline' => Branding::schoolTagline(),
            'logoPath' => Branding::logoPublicPath(),
            'generatedAt' => now(),
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->stream("laporan-{$periodMeta['period']}-{$kelasSlug}.pdf");
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

        $kelasList = Kelas::orderBy('nama_kelas')->get();

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

        $rekapKelas = Kelas::query()
            ->select(['id', 'nama_kelas'])
            ->when($kelasId, function ($query, $kelasId) {
                $query->where('id', $kelasId);
            })
            ->withCount([
                'siswa as total_siswa',
                'siswa as total_masuk' => function ($query) use ($tanggal) {
                    $query->whereHas('absensi', function ($absensiQuery) use ($tanggal) {
                        $absensiQuery->whereDate('tanggal', $tanggal);
                    });
                },
                'siswa as total_pulang' => function ($query) use ($tanggal) {
                    $query->whereHas('absensi', function ($absensiQuery) use ($tanggal) {
                        $absensiQuery->whereDate('tanggal', $tanggal)
                            ->whereNotNull('jam_pulang');
                    });
                },
            ])
            ->orderBy('nama_kelas')
            ->get();

        $siswaBaseQuery = Siswa::with('kelas')
            ->when($kelasId, function ($query, $kelasId) {
                $query->where('kelas_id', $kelasId);
            });

        $siswaSudahMasukIds = Absensi::whereDate('tanggal', $tanggal)
            ->when($kelasId, function ($query, $kelasId) {
                $query->whereHas('siswa', function ($siswaQuery) use ($kelasId) {
                    $siswaQuery->where('kelas_id', $kelasId);
                });
            })
            ->pluck('siswa_id');

        $siswaBelumMasuk = (clone $siswaBaseQuery)
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

        return view('absensi.riwayat', [
            'riwayat' => $riwayat,
            'kelasList' => $kelasList,
            'tanggal' => $tanggal,
            'kelasId' => $kelasId,
            'q' => $q,
            'statusFilter' => $statusFilter,
            'sumberFilter' => $sumberFilter,
            'terlambatFilter' => $terlambatFilter,
            'perPage' => $perPage,
            'summary' => $summary,
            'rekapKelas' => $rekapKelas,
            'siswaBelumMasuk' => $siswaBelumMasuk,
            'siswaBelumPulang' => $siswaBelumPulang,
        ]);
    }

    public function exportRiwayat(Request $request)
    {
        $tanggal = $request->input('tanggal', now()->toDateString());
        $kelasId = $request->input('kelas_id');
        $q = trim((string) $request->input('q', ''));
        $statusFilter = $request->input('status_filter');
        $sumberFilter = $request->input('sumber_filter');
        $terlambatFilter = $request->input('terlambat_filter');

        $riwayat = $this->absensi->buildRiwayatQuery(
            $tanggal,
            $kelasId,
            $q,
            $statusFilter,
            $sumberFilter,
            $terlambatFilter
        )
            ->orderBy('jam_masuk')
            ->get();

        $filename = 'riwayat_absensi_' . $tanggal;
        if (!empty($kelasId)) {
            $filename .= '_kelas_' . $kelasId;
        }
        $filename .= '.csv';

        return response()->streamDownload(function () use ($riwayat) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'NIS',
                'Nama',
                'Kelas',
                'Tanggal',
                'Jam Masuk',
                'Jam Pulang',
                'Status',
                'Keterangan',
                'Sumber',
                'Terlambat',
                'Foto Masuk',
                'Foto Pulang',
            ]);

            foreach ($riwayat as $item) {
                fputcsv($handle, [
                    $item->siswa->nis ?? '',
                    $item->siswa->nama ?? '',
                    $item->siswa->kelas->nama_kelas ?? '',
                    $item->tanggal,
                    $item->jam_masuk ?? '',
                    $item->jam_pulang ?? '',
                    $item->status,
                    $item->keterangan ?? '',
                    $item->sumber ?? '',
                    $item->terlambat ? 'ya' : 'tidak',
                    $item->foto_masuk ?? '',
                    $item->foto_pulang ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function updateStatus(Request $request, Absensi $absensi)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:hadir,izin,sakit,alpha'],
            'tanggal' => ['nullable', 'date'],
            'kelas_id' => ['nullable'],
            'q' => ['nullable', 'string'],
            'status_filter' => ['nullable', 'in:hadir,izin,sakit,alpha'],
            'sumber_filter' => ['nullable', 'in:scan_qr,manual,auto_alpha'],
            'terlambat_filter' => ['nullable', 'in:ya,tidak'],
            'per_page' => ['nullable'],
            'page' => ['nullable'],
        ]);

        $absensi->update([
            'status' => $validated['status'],
        ]);

        ActivityLogger::log(
            'absensi.update_status',
            'Status absensi diperbarui.',
            $absensi,
            [
                'status' => $validated['status'],
            ]
        );

        return redirect('/absensi/riwayat?' . http_build_query([
            'tanggal' => $validated['tanggal'] ?? now()->toDateString(),
            'kelas_id' => $validated['kelas_id'] ?? null,
            'q' => $validated['q'] ?? null,
            'status_filter' => $validated['status_filter'] ?? null,
            'sumber_filter' => $validated['sumber_filter'] ?? null,
            'terlambat_filter' => $validated['terlambat_filter'] ?? null,
            'per_page' => $validated['per_page'] ?? null,
            'page' => $validated['page'] ?? null,
        ]))->with('success', 'Status absensi berhasil diperbarui.');
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
            'per_page' => ['nullable'],
        ]);

        $tanggal = $validated['tanggal'];
        $kelasId = $validated['kelas_id'] ?? null;
        $q = trim((string) ($validated['q'] ?? ''));
        $statusFilter = $validated['status_filter'] ?? null;
        $sumberFilter = $validated['sumber_filter'] ?? null;
        $terlambatFilter = $validated['terlambat_filter'] ?? null;

        if ($this->absensi->markAlphaWouldBeExcludedByFilters($statusFilter, $sumberFilter, $terlambatFilter)) {
            return redirect('/absensi/riwayat?' . http_build_query([
                'tanggal' => $validated['tanggal'],
                'kelas_id' => $validated['kelas_id'] ?? null,
                'q' => $validated['q'] ?? null,
                'status_filter' => $statusFilter,
                'sumber_filter' => $sumberFilter,
                'terlambat_filter' => $terlambatFilter,
                'per_page' => $validated['per_page'] ?? null,
            ]))->with('success', 'Tidak ada data alpha otomatis yang cocok dengan filter aktif.');
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
                [
                    'siswa_id' => $siswaId,
                    'tanggal' => $tanggal,
                ]
            );

            $totalDitandai++;
        }

        return redirect('/absensi/riwayat?' . http_build_query([
            'tanggal' => $validated['tanggal'],
            'kelas_id' => $validated['kelas_id'] ?? null,
            'q' => $validated['q'] ?? null,
            'status_filter' => $validated['status_filter'] ?? null,
            'sumber_filter' => $validated['sumber_filter'] ?? null,
            'terlambat_filter' => $validated['terlambat_filter'] ?? null,
            'per_page' => $validated['per_page'] ?? null,
        ]))->with('success', "Berhasil menandai {$totalDitandai} siswa sebagai alpha.");
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
            'per_page' => ['nullable'],
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

        return redirect('/absensi/riwayat?' . http_build_query([
            'tanggal' => $validated['tanggal'],
            'kelas_id' => $validated['kelas_id'] ?? null,
            'q' => $validated['q'] ?? null,
            'status_filter' => $validated['status_filter'] ?? null,
            'sumber_filter' => $validated['sumber_filter'] ?? null,
            'terlambat_filter' => $validated['terlambat_filter'] ?? null,
            'per_page' => $validated['per_page'] ?? null,
        ]))->with('success', "Berhasil membatalkan {$totalDibatalkan} data alpha otomatis.");
    }

    public function destroy(Request $request, Absensi $absensi)
    {
        $validated = $request->validate([
            'tanggal' => ['nullable', 'date'],
            'kelas_id' => ['nullable'],
            'q' => ['nullable', 'string'],
            'status_filter' => ['nullable', 'in:hadir,izin,sakit,alpha'],
            'sumber_filter' => ['nullable', 'in:scan_qr,manual,auto_alpha'],
            'terlambat_filter' => ['nullable', 'in:ya,tidak'],
            'per_page' => ['nullable'],
            'page' => ['nullable'],
        ]);

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

        ActivityLogger::log(
            'absensi.destroy',
            'Data absensi dihapus.',
            null,
            $properties
        );

        return redirect('/absensi/riwayat?' . http_build_query([
            'tanggal' => $validated['tanggal'] ?? now()->toDateString(),
            'kelas_id' => $validated['kelas_id'] ?? null,
            'q' => $validated['q'] ?? null,
            'status_filter' => $validated['status_filter'] ?? null,
            'sumber_filter' => $validated['sumber_filter'] ?? null,
            'terlambat_filter' => $validated['terlambat_filter'] ?? null,
            'per_page' => $validated['per_page'] ?? null,
            'page' => $validated['page'] ?? null,
        ]))->with('success', 'Data absensi berhasil dihapus.');
    }

    public function updateWaktu(Request $request, Absensi $absensi)
    {
        $validated = $request->validate([
            'jam_masuk' => ['nullable', 'date_format:H:i'],
            'jam_pulang' => ['nullable', 'date_format:H:i'],
            'tanggal' => ['nullable', 'date'],
            'kelas_id' => ['nullable'],
            'q' => ['nullable', 'string'],
            'status_filter' => ['nullable', 'in:hadir,izin,sakit,alpha'],
            'sumber_filter' => ['nullable', 'in:scan_qr,manual,auto_alpha'],
            'terlambat_filter' => ['nullable', 'in:ya,tidak'],
            'per_page' => ['nullable'],
            'page' => ['nullable'],
        ]);

        $jamMasuk = $validated['jam_masuk'] ?? null;
        $jamPulang = $validated['jam_pulang'] ?? null;

        if ($jamPulang !== null && $jamMasuk === null) {
            return redirect('/absensi/riwayat?' . http_build_query([
                'tanggal' => $validated['tanggal'] ?? now()->toDateString(),
                'kelas_id' => $validated['kelas_id'] ?? null,
                'q' => $validated['q'] ?? null,
                'status_filter' => $validated['status_filter'] ?? null,
                'sumber_filter' => $validated['sumber_filter'] ?? null,
                'terlambat_filter' => $validated['terlambat_filter'] ?? null,
                'per_page' => $validated['per_page'] ?? null,
                'page' => $validated['page'] ?? null,
            ]))->with('error', 'Jam pulang tidak bisa diisi jika jam masuk kosong.');
        }

        if ($jamMasuk !== null && $jamPulang !== null && $jamPulang < $jamMasuk) {
            return redirect('/absensi/riwayat?' . http_build_query([
                'tanggal' => $validated['tanggal'] ?? now()->toDateString(),
                'kelas_id' => $validated['kelas_id'] ?? null,
                'q' => $validated['q'] ?? null,
                'status_filter' => $validated['status_filter'] ?? null,
                'sumber_filter' => $validated['sumber_filter'] ?? null,
                'terlambat_filter' => $validated['terlambat_filter'] ?? null,
                'per_page' => $validated['per_page'] ?? null,
                'page' => $validated['page'] ?? null,
            ]))->with('error', 'Jam pulang tidak boleh lebih kecil dari jam masuk.');
        }

        $absensi->update([
            'jam_masuk' => $jamMasuk ? $jamMasuk . ':00' : null,
            'jam_pulang' => $jamPulang ? $jamPulang . ':00' : null,
        ]);

        ActivityLogger::log(
            'absensi.update_waktu',
            'Jam masuk/pulang absensi diperbarui.',
            $absensi,
            [
                'jam_masuk' => $jamMasuk,
                'jam_pulang' => $jamPulang,
            ]
        );

        return redirect('/absensi/riwayat?' . http_build_query([
            'tanggal' => $validated['tanggal'] ?? now()->toDateString(),
            'kelas_id' => $validated['kelas_id'] ?? null,
            'q' => $validated['q'] ?? null,
            'status_filter' => $validated['status_filter'] ?? null,
            'sumber_filter' => $validated['sumber_filter'] ?? null,
            'terlambat_filter' => $validated['terlambat_filter'] ?? null,
            'per_page' => $validated['per_page'] ?? null,
            'page' => $validated['page'] ?? null,
        ]))->with('success', 'Jam masuk/pulang berhasil diperbarui.');
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
                'msg' => 'Siswa tidak ditemukan'
            ]);
        }

        $today = now()->toDateString();
        $absensiHariIni = Absensi::where('siswa_id', $siswa->id)
            ->whereDate('tanggal', $today)
            ->first();

        $canMasuk = $absensiHariIni === null;
        $canPulang = $absensiHariIni !== null && $absensiHariIni->jam_pulang === null;
        $alreadyComplete = $absensiHariIni !== null && $absensiHariIni->jam_pulang !== null;

        return response()->json([
            'status' => 'ok',
            'siswa_id' => $siswa->id,
            'nama' => $siswa->nama,
            'can_masuk' => $canMasuk,
            'can_pulang' => $canPulang,
            'already_complete' => $alreadyComplete,
            'jam_masuk' => $absensiHariIni?->jam_masuk,
            'jam_pulang' => $absensiHariIni?->jam_pulang,
        ]);
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
                'msg' => 'Siswa tidak ditemukan'
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
                'msg' => 'Absensi masuk hari ini sudah tercatat'
            ], 409);
        }

        if ($jenis === 'pulang' && !$absensiHariIni) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Belum ada absensi masuk hari ini'
            ], 409);
        }

        if ($jenis === 'pulang' && $absensiHariIni->jam_pulang !== null) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Absensi pulang hari ini sudah tercatat'
            ], 409);
        }

        $fotoBase64 = $request->foto;
        $mime = null;
        $base64Body = $fotoBase64;

        if (preg_match('/^data:image\/(jpeg|jpg|png);base64,(.+)$/', $fotoBase64, $matches)) {
            $mime = strtolower($matches[1]);
            $base64Body = $matches[2];
        }

        $base64Body = str_replace(' ', '+', $base64Body);
        $imageBinary = base64_decode($base64Body, true);

        if ($imageBinary === false) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Format foto tidak valid'
            ], 422);
        }

        if (strlen($imageBinary) > 5 * 1024 * 1024) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Ukuran foto terlalu besar (maksimal 5MB)'
            ], 422);
        }

        $imageInfo = @getimagesizefromstring($imageBinary);
        if ($imageInfo === false || !in_array($imageInfo['mime'] ?? '', ['image/jpeg', 'image/png'], true)) {
            return response()->json([
                'status' => 'error',
                'msg' => 'File foto harus berupa JPEG atau PNG yang valid'
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
                'msg' => 'Foto gagal disimpan di server. Periksa permission storage dan symlink public/storage.'
            ], 500);
        }

        if ($jenis === 'masuk') {
            try {
                $timing = $this->absensi->evaluateMasukTiming(now()->toTimeString());
                $isTerlambat = $timing['is_late'];

                $createdAbsensi = Absensi::create([
                    'siswa_id'   => $siswa->id,
                    'tanggal'    => $today,
                    'jam_masuk'  => now()->toTimeString(),
                    'foto_masuk' => $filename,
                    'status'     => 'hadir',
                    'sumber'     => 'scan_qr',
                    'terlambat'  => $isTerlambat,
                ]);

                ActivityLogger::log(
                    'absensi.scan_masuk',
                    "Absensi masuk dari scanner disimpan untuk {$siswa->nama}.",
                    $createdAbsensi,
                    [
                        'siswa_id' => $siswa->id,
                        'tanggal' => $today,
                        'terlambat' => $isTerlambat,
                    ]
                );
            } catch (QueryException $e) {
                if ((string) $e->getCode() === '23000') {
                    Storage::disk('public')->delete($relativePath);
                    return response()->json([
                        'status' => 'error',
                        'msg' => 'Absensi masuk hari ini sudah tercatat'
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
                    [
                        'siswa_id' => $siswa->id,
                        'tanggal' => $today,
                    ]
                );
            } catch (\Throwable $e) {
                Storage::disk('public')->delete($relativePath);
                throw $e;
            }
        }

        return response()->json([
            'status' => 'ok',
            'nama'   => $siswa->nama,
            'jenis'  => $jenis,
        ]);
    }

}
