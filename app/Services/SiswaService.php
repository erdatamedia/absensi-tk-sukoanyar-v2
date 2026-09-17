<?php

namespace App\Services;

use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class SiswaService
{
    public function validate(Request $request, ?Siswa $siswa = null): array
    {
        return $request->validate([
            'nis' => [
                'required',
                'string',
                'max:50',
                Rule::unique('siswa', 'nis')->ignore($siswa?->id),
            ],
            'nama' => ['required', 'string', 'max:100'],
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
            'jenis_kelamin' => ['required', 'in:L,P'],
            'tanggal_lahir' => ['nullable', 'date'],
            'alamat' => ['nullable', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /**
     * @return array{created:int,updated:int,skipped:int}|array{error:string}
     */
    public function import(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $rows = collect($spreadsheet->getActiveSheet()->toArray(null, true, true, false));

        $headerIndex = $rows->search(function (array $row) {
            return Str::upper(trim((string) ($row[1] ?? ''))) === 'NIS'
                && Str::contains(Str::upper(trim((string) ($row[2] ?? ''))), 'NAMA SISWA');
        });

        if ($headerIndex === false) {
            return ['error' => 'Format file tidak sesuai template data siswa.'];
        }

        $dataRows = $rows->slice($headerIndex + 1)
            ->filter(fn (array $row) => filled($row[1] ?? null) && filled($row[2] ?? null));

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($dataRows as $row) {
            $payload = $this->mapImportRow($row);

            if (! $payload) {
                $stats['skipped']++;
                continue;
            }

            $siswa = Siswa::where('nis', $payload['nis'])->first();

            if ($siswa) {
                $siswa->update($payload);
                $stats['updated']++;
                continue;
            }

            Siswa::create($payload);
            $stats['created']++;
        }

        return $stats;
    }

    protected function mapImportRow(array $row): ?array
    {
        $nis = trim((string) ($row[1] ?? ''));
        $nama = trim((string) ($row[2] ?? ''));

        if ($nis === '' || $nama === '') {
            return null;
        }

        $jenisKelamin = Str::upper(trim((string) ($row[4] ?? '')));
        if (! in_array($jenisKelamin, ['L', 'P'], true)) {
            $jenisKelamin = 'L';
        }

        return [
            'nis' => $nis,
            'nama' => $nama,
            'tanggal_lahir' => $this->parseTanggalLahir($row[3] ?? null),
            'jenis_kelamin' => $jenisKelamin,
            'kelas_id' => $this->resolveKelasId((string) ($row[5] ?? '')),
            'keterangan' => $this->cleanNullableString($row[6] ?? null),
            'alamat' => $this->cleanNullableString($row[7] ?? null),
        ];
    }

    protected function parseTanggalLahir(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject($value))->toDateString();
            }

            if ($value instanceof \DateTimeInterface) {
                return Carbon::instance($value)->toDateString();
            }

            $text = trim((string) $value);
            foreach (['d/m/Y', 'd-m-Y', 'Y-m-d'] as $format) {
                try {
                    return Carbon::createFromFormat($format, $text)->toDateString();
                } catch (\Throwable) {
                }
            }

            return Carbon::parse($text)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function resolveKelasId(string $kelasLabel): int
    {
        $kelasLabel = trim($kelasLabel);
        $normalized = Str::upper($kelasLabel);

        $candidates = Collection::make([
            $kelasLabel,
            'TK ' . $normalized,
            'Kelompok ' . $normalized,
        ])->filter()->unique();

        $kelas = Kelas::query()
            ->where(function ($query) use ($candidates) {
                foreach ($candidates as $candidate) {
                    $query->orWhereRaw('UPPER(nama_kelas) = ?', [Str::upper($candidate)]);
                }
            })
            ->first();

        if (! $kelas) {
            $kelas = Kelas::create([
                'nama_kelas' => Str::startsWith($normalized, 'TK ') ? $normalized : 'TK ' . $normalized,
                'tahun_ajaran' => $this->defaultTahunAjaran(),
            ]);
        }

        return $kelas->id;
    }

    protected function defaultTahunAjaran(): string
    {
        $existing = Kelas::query()->whereNotNull('tahun_ajaran')->value('tahun_ajaran');

        if ($existing) {
            return $existing;
        }

        $startYear = now()->month >= 7 ? now()->year : now()->year - 1;

        return $startYear . '/' . ($startYear + 1);
    }

    protected function cleanNullableString(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : Str::limit($text, 255, '');
    }
}
