<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Services\AppSettingService;
use App\Support\Branding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AppSettingController extends Controller
{
    public function __construct(private AppSettingService $appSettingService)
    {
    }

    public function edit()
    {
        return view('settings.school', [
            'settings' => [
                'school_name' => Branding::schoolName(),
                'school_tagline' => Branding::schoolTagline(),
                'school_logo_url' => Branding::logoUrl(),
                'operational_start' => Branding::operationalStart(),
                'operational_end' => Branding::operationalEnd(),
            ],
            'previewCard' => [
                'nama' => 'Budi Santoso',
                'nis' => '001',
                'kelas' => 'TK A',
                'tanggal_lahir' => '01/01/2020',
                'jenis_kelamin' => 'Laki-laki',
                'alamat' => 'Sukoanyar',
            ],
            'kelasList' => Kelas::orderBy('nama_kelas')->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $result = $this->appSettingService->update($request);

        if (! $result['ok']) {
            return back()->withErrors(['school_logo' => $result['message']])->withInput();
        }

        return redirect()->route('settings.school.edit')->with('success', 'Pengaturan sekolah berhasil diperbarui.');
    }
}
