<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Services\SiswaService;
use App\Support\Branding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class SiswaController extends Controller
{
    public function __construct(private SiswaService $siswaService)
    {
    }

    public function index()
    {
        $siswa = Siswa::with('kelas')
            ->orderBy('nama')
            ->get();
        $kelas = Kelas::orderBy('nama_kelas')->get();

        return view('siswa.index', compact('siswa', 'kelas'));
    }

    public function create()
    {
        $kelas = Kelas::orderBy('nama_kelas')->get();

        return view('siswa.create', compact('kelas'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->siswaService->validate($request);

        Siswa::create($validated);

        return redirect()->route('siswa.index')->with('success', 'Siswa berhasil ditambahkan.');
    }

    public function show(Siswa $siswa)
    {
        $siswa->load('kelas');

        return view('siswa.show', compact('siswa'));
    }

    public function edit(Siswa $siswa)
    {
        $kelas = Kelas::orderBy('nama_kelas')->get();

        return view('siswa.edit', [
            'siswa' => $siswa,
            'kelas' => $kelas,
        ]);
    }

    public function update(Request $request, Siswa $siswa): RedirectResponse
    {
        $validated = $this->siswaService->validate($request, $siswa);

        $siswa->update($validated);

        return redirect()->route('siswa.index')->with('success', 'Data siswa berhasil diperbarui.');
    }

    public function destroy(Siswa $siswa): RedirectResponse
    {
        $siswa->delete();

        return redirect()->route('siswa.index')->with('success', 'Data siswa berhasil dihapus.');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        $result = $this->siswaService->import($request->file('file'));

        if (isset($result['error'])) {
            return back()->withErrors(['file' => $result['error']])->withInput();
        }

        return redirect()->route('siswa.index')->with(
            'success',
            "Impor selesai. {$result['created']} siswa baru, {$result['updated']} diperbarui, {$result['skipped']} dilewati."
        );
    }

    public function cardPdf(Request $request, Siswa $siswa)
    {
        $siswa->load('kelas');
        $showAddress = $request->boolean('show_address', true);

        $pdf = Pdf::loadView('siswa.pdf-card', [
            'siswa' => $this->presentSiswaCard($siswa, $showAddress),
            'sekolah' => Branding::schoolName(),
            'tagline' => Branding::schoolTagline(),
            'logoPath' => Branding::logoPublicPath(),
            'brandInitials' => Branding::initials(),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('kartu-siswa-' . Str::slug($siswa->nama) . '.pdf');
    }

    public function massCardPdf(Request $request)
    {
        $request->validate([
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'per_page' => ['nullable', 'integer'],
            'show_address' => ['nullable', 'boolean'],
        ]);

        $showAddress = $request->boolean('show_address', true);
        $kelasId = $request->integer('kelas_id');

        $query = Siswa::with('kelas')
            ->orderBy('kelas_id')
            ->orderBy('nama');

        if ($kelasId) {
            $query->where('kelas_id', $kelasId);
        }

        $students = $query->get()
            ->map(fn (Siswa $siswa) => $this->presentSiswaCard($siswa, $showAddress));

        abort_if($students->isEmpty(), 404, 'Belum ada siswa untuk dicetak.');

        $perPage = (int) $request->integer('per_page', 8);
        if (! in_array($perPage, [1, 8], true)) {
            $perPage = 8;
        }

        $view = $perPage === 1 ? 'siswa.pdf-cards-single-per-page' : 'siswa.pdf-cards-mass';
        $paper = 'a4';
        $orientation = 'portrait';

        $pdf = Pdf::loadView($view, [
            'cards' => $students->chunk($perPage),
            'showAddress' => $showAddress,
            'sekolah' => Branding::schoolName(),
            'tagline' => Branding::schoolTagline(),
            'logoPath' => Branding::logoPublicPath(),
            'brandInitials' => Branding::initials(),
        ]);

        $pdf->setPaper($paper, $orientation);

        return $pdf->stream('kartu-siswa-' . $perPage . '-per-halaman.pdf');
    }

    protected function presentSiswaCard(Siswa $siswa, bool $showAddress = true): array
    {
        return [
            'id' => $siswa->id,
            'nis' => $siswa->nis,
            'nama' => $siswa->nama,
            'kelas' => $siswa->kelas->nama_kelas ?? 'Kelas',
            'tanggal_lahir' => $siswa->tanggal_lahir ? date('d/m/Y', strtotime($siswa->tanggal_lahir)) : '-',
            'jenis_kelamin' => $siswa->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan',
            'alamat' => $showAddress ? ($siswa->alamat ?: '-') : null,
            'show_address' => $showAddress,
            'token_preview' => Str::upper(Str::limit($siswa->qr_token, 20, '...')),
            'qr_data_uri' => 'data:image/svg+xml;base64,' . base64_encode(QrCode::format('svg')->size(220)->margin(0)->generate($siswa->qr_token)),
        ];
    }

}
