<?php

namespace App\Services;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Support\Branding;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class AbsensiService
{
    public function buildRiwayatQuery(
        string $tanggal,
        ?string $kelasId,
        string $q = '',
        ?string $statusFilter = null,
        ?string $sumberFilter = null,
        ?string $terlambatFilter = null
    ) {
        return Absensi::with(['siswa.kelas'])
            ->whereDate('tanggal', $tanggal)
            ->when($kelasId, function ($query, $kelasId) {
                $query->whereHas('siswa', function ($siswaQuery) use ($kelasId) {
                    $siswaQuery->where('kelas_id', $kelasId);
                });
            })
            ->when($statusFilter, function ($query, $statusFilter) {
                $query->where('status', $statusFilter);
            })
            ->when($sumberFilter, function ($query, $sumberFilter) {
                $query->where('sumber', $sumberFilter);
            })
            ->when($terlambatFilter === 'ya', function ($query) {
                $query->where('terlambat', true);
            })
            ->when($terlambatFilter === 'tidak', function ($query) {
                $query->where('terlambat', false);
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->whereHas('siswa', function ($siswaQuery) use ($q) {
                    $siswaQuery->where(function ($inner) use ($q) {
                        $inner->where('nama', 'like', '%' . $q . '%')
                            ->orWhere('nis', 'like', '%' . $q . '%');
                    });
                });
            });
    }

    public function markAlphaWouldBeExcludedByFilters(
        ?string $statusFilter,
        ?string $sumberFilter,
        ?string $terlambatFilter
    ): bool {
        if ($statusFilter !== null && $statusFilter !== 'alpha') {
            return true;
        }

        if ($sumberFilter !== null && $sumberFilter !== 'auto_alpha') {
            return true;
        }

        if ($terlambatFilter !== null && $terlambatFilter !== 'tidak') {
            return true;
        }

        return false;
    }

    public function getMonitorData(string $tanggal, int $limit): array
    {
        $summary = [
            'masuk' => Absensi::whereDate('tanggal', $tanggal)->count(),
            'pulang' => Absensi::whereDate('tanggal', $tanggal)->whereNotNull('jam_pulang')->count(),
            'alpha' => Absensi::whereDate('tanggal', $tanggal)->where('status', 'alpha')->count(),
            'belum_pulang' => Absensi::whereDate('tanggal', $tanggal)->whereNull('jam_pulang')->count(),
        ];

        $aktivitas = Absensi::with(['siswa.kelas'])
            ->whereDate('tanggal', $tanggal)
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        return [
            'summary' => $summary,
            'aktivitas' => $aktivitas,
        ];
    }

    public function getMasukCutoff(): string
    {
        $cutoff = Branding::operationalStart();

        if (!preg_match('/^\d{2}:\d{2}$/', $cutoff)) {
            return '06:30';
        }

        return $cutoff;
    }

    public function getOperationalEnd(): string
    {
        $end = Branding::operationalEnd();

        if (!preg_match('/^\d{2}:\d{2}$/', $end)) {
            return '12:00';
        }

        return $end;
    }

    public function evaluateMasukTiming(string $time): array
    {
        $start = $this->getMasukCutoff();
        $end = $this->getOperationalEnd();
        $timeValue = Carbon::createFromFormat('H:i:s', $time);
        $startValue = Carbon::createFromFormat('H:i', $start);
        $endValue = Carbon::createFromFormat('H:i', $end);

        return [
            'start' => $start,
            'end' => $end,
            'is_before_start' => $timeValue->lt($startValue),
            'is_after_end' => $timeValue->gt($endValue),
            'is_late' => $timeValue->lt($startValue) || $timeValue->gt($endValue),
        ];
    }

    public function normalizeDatasetImage(string $imageBinary, array $imageInfo): ?string
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
            return null;
        }

        $sourceImage = @imagecreatefromstring($imageBinary);

        if ($sourceImage === false) {
            return null;
        }

        $width = (int) ($imageInfo[0] ?? imagesx($sourceImage));
        $height = (int) ($imageInfo[1] ?? imagesy($sourceImage));
        $maxDimension = 1280;
        $scale = max($width, $height) > $maxDimension
            ? ($maxDimension / max($width, $height))
            : 1;

        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($targetImage === false) {
            imagedestroy($sourceImage);

            return null;
        }

        $background = imagecolorallocate($targetImage, 255, 255, 255);
        imagefill($targetImage, 0, 0, $background);

        imagecopyresampled(
            $targetImage,
            $sourceImage,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $width,
            $height
        );

        ob_start();
        imagejpeg($targetImage, null, 72);
        $normalized = ob_get_clean();

        imagedestroy($sourceImage);
        imagedestroy($targetImage);

        return $normalized !== false ? $normalized : null;
    }

    public function buildDailyClassReports(
        string $startDate,
        string $endDate,
        mixed $kelasId = null,
        bool $isSingleDay = true
    ): Collection {
        return Kelas::query()
            ->when($kelasId, function ($query, $kelasId) {
                $query->where('id', $kelasId);
            })
            ->with([
                'siswa' => function ($query) {
                    $query->orderBy('nama');
                },
                'siswa.absensi' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('tanggal', [$startDate, $endDate])
                        ->orderBy('tanggal')
                        ->orderBy('jam_masuk');
                },
            ])
            ->orderBy('nama_kelas')
            ->get()
            ->map(function (Kelas $kelas) use ($isSingleDay) {
                $rows = $kelas->siswa->map(function (Siswa $siswa) {
                    $records = $siswa->absensi->sortBy([
                        ['tanggal', 'asc'],
                        ['jam_masuk', 'asc'],
                    ])->values();
                    $latest = $records->last();

                    $totalHadir = $records->where('status', 'hadir')->count();
                    $totalIzin = $records->where('status', 'izin')->count();
                    $totalSakit = $records->where('status', 'sakit')->count();
                    $totalAlpha = $records->where('status', 'alpha')->count();
                    $totalTerlambat = $records->where('terlambat', true)->count();
                    $totalPulang = $records->filter(fn ($record) => !empty($record->jam_pulang))->count();
                    $fotoBukti = $records->filter(fn ($record) => !empty($record->foto_masuk))->count();

                    $status = $latest?->status;
                    $statusLabel = $records->isEmpty()
                        ? 'Belum Masuk'
                        : ucfirst((string) $status);

                    return [
                        'siswa' => $siswa,
                        'absensi' => $latest,
                        'records' => $records,
                        'nis' => $siswa->nis,
                        'nama' => $siswa->nama,
                        'jenis_kelamin' => $siswa->jenis_kelamin,
                        'status' => $status,
                        'status_label' => $statusLabel,
                        'jam_masuk' => $latest?->jam_masuk,
                        'jam_pulang' => $latest?->jam_pulang,
                        'sumber' => $latest?->sumber,
                        'keterangan' => $latest?->keterangan,
                        'terlambat' => (bool) ($latest?->terlambat ?? false),
                        'foto_masuk' => $latest?->foto_masuk,
                        'foto_pulang' => $latest?->foto_pulang,
                        'total_records' => $records->count(),
                        'total_hadir' => $totalHadir,
                        'total_izin' => $totalIzin,
                        'total_sakit' => $totalSakit,
                        'total_alpha' => $totalAlpha,
                        'total_terlambat' => $totalTerlambat,
                        'total_pulang' => $totalPulang,
                        'foto_bukti_count' => $fotoBukti,
                        'last_tanggal' => $latest?->tanggal,
                        'last_jam_masuk' => $latest?->jam_masuk,
                        'last_jam_pulang' => $latest?->jam_pulang,
                        'last_sumber' => $latest?->sumber,
                        'last_foto_masuk' => $latest?->foto_masuk,
                        'last_foto_pulang' => $latest?->foto_pulang,
                    ];
                })->map(function (array $row) use ($isSingleDay) {
                    if ($isSingleDay) {
                        return $row;
                    }

                    if ($row['total_records'] === 0) {
                        $row['status_label'] = 'Tanpa Catatan';
                    } else {
                        $parts = [];
                        foreach ([
                            'hadir' => $row['total_hadir'],
                            'izin' => $row['total_izin'],
                            'sakit' => $row['total_sakit'],
                            'alpha' => $row['total_alpha'],
                        ] as $label => $count) {
                            if ($count > 0) {
                                $parts[] = ucfirst($label) . ' ' . $count . 'x';
                            }
                        }

                        $row['status_label'] = implode(' · ', $parts);
                    }

                    return $row;
                })->values();

                $summary = [
                    'total_siswa' => $rows->count(),
                    'hadir' => $isSingleDay ? $rows->where('status', 'hadir')->count() : $rows->sum('total_hadir'),
                    'izin' => $isSingleDay ? $rows->where('status', 'izin')->count() : $rows->sum('total_izin'),
                    'sakit' => $isSingleDay ? $rows->where('status', 'sakit')->count() : $rows->sum('total_sakit'),
                    'alpha' => $isSingleDay ? $rows->where('status', 'alpha')->count() : $rows->sum('total_alpha'),
                    'belum_masuk' => $rows->where('total_records', 0)->count(),
                    'sudah_pulang' => $isSingleDay ? $rows->filter(fn ($row) => !empty($row['jam_pulang']))->count() : $rows->sum('total_pulang'),
                    'belum_pulang' => $isSingleDay
                        ? $rows->filter(fn ($row) => !empty($row['jam_masuk']) && empty($row['jam_pulang']))->count()
                        : $rows->sum(fn ($row) => max(($row['total_hadir'] ?? 0) - ($row['total_pulang'] ?? 0), 0)),
                    'terlambat' => $isSingleDay ? $rows->where('terlambat', true)->count() : $rows->sum('total_terlambat'),
                    'students_with_records' => $rows->where('total_records', '>', 0)->count(),
                    'foto_bukti' => $isSingleDay ? $rows->filter(fn ($row) => !empty($row['foto_masuk']))->count() : $rows->sum('foto_bukti_count'),
                ];

                return [
                    'kelas' => $kelas,
                    'rows' => $rows,
                    'summary' => $summary,
                ];
            })
            ->values();
    }

    public function buildDailySummaryFromReports(Collection $classReports): array
    {
        return [
            'total_siswa' => $classReports->sum(fn ($report) => $report['summary']['total_siswa']),
            'hadir' => $classReports->sum(fn ($report) => $report['summary']['hadir']),
            'izin' => $classReports->sum(fn ($report) => $report['summary']['izin']),
            'sakit' => $classReports->sum(fn ($report) => $report['summary']['sakit']),
            'alpha' => $classReports->sum(fn ($report) => $report['summary']['alpha']),
            'belum_masuk' => $classReports->sum(fn ($report) => $report['summary']['belum_masuk']),
            'sudah_pulang' => $classReports->sum(fn ($report) => $report['summary']['sudah_pulang']),
            'belum_pulang' => $classReports->sum(fn ($report) => $report['summary']['belum_pulang']),
            'terlambat' => $classReports->sum(fn ($report) => $report['summary']['terlambat']),
            'students_with_records' => $classReports->sum(fn ($report) => $report['summary']['students_with_records'] ?? 0),
            'foto_bukti' => $classReports->sum(fn ($report) => $report['summary']['foto_bukti'] ?? 0),
        ];
    }

    public function buildTrendData(string $startDate, string $endDate, mixed $kelasId = null): array
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        $rows = Absensi::query()
            ->selectRaw('tanggal,
                SUM(CASE WHEN status = "hadir" THEN 1 ELSE 0 END) as hadir_count,
                SUM(CASE WHEN status = "izin" THEN 1 ELSE 0 END) as izin_count,
                SUM(CASE WHEN status = "alpha" THEN 1 ELSE 0 END) as alpha_count')
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->when($kelasId, function ($query, $kelasId) {
                $query->whereHas('siswa', function ($siswaQuery) use ($kelasId) {
                    $siswaQuery->where('kelas_id', $kelasId);
                });
            })
            ->groupBy('tanggal')
            ->orderBy('tanggal')
            ->get()
            ->keyBy('tanggal');

        $classRows = Absensi::query()
            ->join('siswa', 'absensi.siswa_id', '=', 'siswa.id')
            ->join('kelas', 'siswa.kelas_id', '=', 'kelas.id')
            ->selectRaw('kelas.nama_kelas as class_name,
                SUM(CASE WHEN absensi.status = "hadir" THEN 1 ELSE 0 END) as total_hadir,
                SUM(CASE WHEN absensi.status = "izin" THEN 1 ELSE 0 END) as total_izin,
                SUM(CASE WHEN absensi.status = "alpha" THEN 1 ELSE 0 END) as total_alpha')
            ->whereBetween('absensi.tanggal', [$startDate, $endDate])
            ->when($kelasId, function ($query, $kelasId) {
                $query->where('kelas.id', $kelasId);
            })
            ->groupBy('kelas.id', 'kelas.nama_kelas')
            ->orderBy('kelas.nama_kelas')
            ->get()
            ->map(function ($row) {
                return [
                    'name' => $row->class_name,
                    'hadir' => (int) $row->total_hadir,
                    'izin' => (int) $row->total_izin,
                    'alpha' => (int) $row->total_alpha,
                    'total' => (int) $row->total_hadir + (int) $row->total_izin + (int) $row->total_alpha,
                ];
            })
            ->values();

        $points = collect();
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $date = $cursor->toDateString();
            $hadir = (int) ($rows[$date]->hadir_count ?? 0);
            $izin = (int) ($rows[$date]->izin_count ?? 0);
            $alpha = (int) ($rows[$date]->alpha_count ?? 0);
            $total = $hadir + $izin + $alpha;
            $points->push([
                'date' => $date,
                'label' => $cursor->translatedFormat('d M'),
                'hadir' => $hadir,
                'izin' => $izin,
                'alpha' => $alpha,
                'total' => $total,
            ]);
            $cursor->addDay();
        }

        $maxValue = max(1, (int) $points->max('total'));

        return [
            'points' => $points,
            'max' => $maxValue,
            'average' => round($points->avg('hadir') ?? 0, 1),
            'peak' => (int) $points->max('hadir'),
            'class_breakdown' => $classRows,
        ];
    }

    public function resolveReportPeriod(Request $request): array
    {
        $period = (string) $request->input('period', 'daily');
        if (!in_array($period, ['daily', 'weekly', 'monthly', 'custom'], true)) {
            $period = 'daily';
        }

        $anchorDate = Carbon::parse($request->input('tanggal', now()->toDateString()));
        $preset = (string) $request->input('preset', '');
        $startInput = $request->input('tanggal_mulai');
        $endInput = $request->input('tanggal_selesai');

        if ($preset === 'this_week') {
            $period = 'weekly';
            $start = $anchorDate->copy()->startOfWeek(Carbon::MONDAY);
            $end = $anchorDate->copy()->endOfWeek(Carbon::SUNDAY);
            $label = 'Mingguan';
        } elseif ($preset === 'this_month') {
            $period = 'monthly';
            $start = $anchorDate->copy()->startOfMonth();
            $end = $anchorDate->copy()->endOfMonth();
            $label = 'Bulanan';
        } elseif ($preset === 'last_7_days') {
            $period = 'custom';
            $end = $anchorDate->copy();
            $start = $anchorDate->copy()->subDays(6);
            $label = 'Custom';
        } elseif ($preset === 'last_30_days') {
            $period = 'custom';
            $end = $anchorDate->copy();
            $start = $anchorDate->copy()->subDays(29);
            $label = 'Custom';
        } elseif ($period === 'weekly') {
            $start = $anchorDate->copy()->startOfWeek(Carbon::MONDAY);
            $end = $anchorDate->copy()->endOfWeek(Carbon::SUNDAY);
            $label = 'Mingguan';
        } elseif ($period === 'monthly') {
            $start = $anchorDate->copy()->startOfMonth();
            $end = $anchorDate->copy()->endOfMonth();
            $label = 'Bulanan';
        } elseif ($period === 'custom') {
            $start = Carbon::parse($startInput ?: $anchorDate->toDateString());
            $end = Carbon::parse($endInput ?: $start->toDateString());
            if ($start->gt($end)) {
                [$start, $end] = [$end, $start];
            }
            $label = 'Custom';
        } else {
            $start = $anchorDate->copy();
            $end = $anchorDate->copy();
            $label = 'Harian';
        }

        $isSingleDay = $start->isSameDay($end);

        return [
            'period' => $period,
            'preset' => $preset,
            'label' => $label,
            'anchor_date' => $anchorDate->toDateString(),
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'start_input' => $startInput ?: $start->toDateString(),
            'end_input' => $endInput ?: $end->toDateString(),
            'is_single_day' => $isSingleDay,
            'range_label' => $isSingleDay
                ? $start->translatedFormat('d F Y')
                : $start->translatedFormat('d F Y') . ' - ' . $end->translatedFormat('d F Y'),
        ];
    }

    public function deleteDatasetFile(?string $filename): void
    {
        if (!$filename) {
            return;
        }

        Storage::disk('public')->delete('dataset/' . $filename);
    }
}
