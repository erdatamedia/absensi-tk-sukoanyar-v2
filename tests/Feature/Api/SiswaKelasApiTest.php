<?php

namespace Tests\Feature\Api;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SiswaKelasApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_crud_kelas_via_api(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $create = $this->actingAs($admin)->postJson('/api/kelas', [
            'nama_kelas' => 'TK A',
            'tahun_ajaran' => '2025/2026',
        ])->assertCreated()->json('kelas');

        $this->actingAs($admin)
            ->patchJson("/api/kelas/{$create['id']}", [
                'nama_kelas' => 'TK A1',
                'tahun_ajaran' => '2025/2026',
            ])
            ->assertOk();

        $this->assertDatabaseHas('kelas', ['id' => $create['id'], 'nama_kelas' => 'TK A1']);

        $this->actingAs($admin)
            ->getJson('/api/kelas')
            ->assertOk()
            ->assertJsonFragment(['nama_kelas' => 'TK A1']);

        $this->actingAs($admin)
            ->deleteJson("/api/kelas/{$create['id']}")
            ->assertOk();

        $this->assertDatabaseMissing('kelas', ['id' => $create['id']]);
    }

    public function test_kelas_with_students_cannot_be_deleted_via_api(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $kelas = Kelas::create(['nama_kelas' => 'TK B', 'tahun_ajaran' => '2025/2026']);
        Siswa::create([
            'nis' => 'S-200',
            'nama' => 'Nadia',
            'kelas_id' => $kelas->id,
            'jenis_kelamin' => 'P',
        ]);

        $this->actingAs($admin)
            ->deleteJson("/api/kelas/{$kelas->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('kelas', ['id' => $kelas->id]);
    }

    public function test_admin_can_crud_siswa_via_api(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $kelas = Kelas::create(['nama_kelas' => 'TK C', 'tahun_ajaran' => '2025/2026']);

        $created = $this->actingAs($admin)->postJson('/api/siswa', [
            'nis' => 'S-300',
            'nama' => 'Fajar',
            'kelas_id' => $kelas->id,
            'jenis_kelamin' => 'L',
        ])->assertCreated()->json('siswa');

        $this->actingAs($admin)
            ->getJson('/api/siswa')
            ->assertOk()
            ->assertJsonFragment(['nis' => 'S-300']);

        $this->actingAs($admin)
            ->patchJson("/api/siswa/{$created['id']}", [
                'nis' => 'S-300',
                'nama' => 'Fajar Ariadi',
                'kelas_id' => $kelas->id,
                'jenis_kelamin' => 'L',
            ])
            ->assertOk();

        $this->assertDatabaseHas('siswa', ['id' => $created['id'], 'nama' => 'Fajar Ariadi']);

        $this->actingAs($admin)
            ->deleteJson("/api/siswa/{$created['id']}")
            ->assertOk();

        $this->assertDatabaseMissing('siswa', ['id' => $created['id']]);
    }

    public function test_duplicate_nis_is_rejected_via_api(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $kelas = Kelas::create(['nama_kelas' => 'TK D', 'tahun_ajaran' => '2025/2026']);
        Siswa::create(['nis' => 'S-400', 'nama' => 'Existing', 'kelas_id' => $kelas->id, 'jenis_kelamin' => 'L']);

        $this->actingAs($admin)->postJson('/api/siswa', [
            'nis' => 'S-400',
            'nama' => 'Duplicate',
            'kelas_id' => $kelas->id,
            'jenis_kelamin' => 'L',
        ])->assertStatus(422);
    }

    public function test_admin_can_import_siswa_via_api(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Kelas::create(['nama_kelas' => 'TK A', 'tahun_ajaran' => '2025/2026']);

        $file = $this->makeImportFile();

        $this->actingAs($admin)
            ->postJson('/api/siswa/import', ['file' => $file])
            ->assertOk()
            ->assertJson(['status' => 'ok', 'created' => 2]);

        $this->assertDatabaseHas('siswa', ['nis' => '2026001', 'nama' => 'Alya Putri']);
    }

    protected function makeImportFile(): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray([
            ['DATA SISWA'],
            [''],
            ['NO.', 'NIS', 'NAMA SISWA', 'TANGGAL LAHIR (dd/mm/yyyy)', 'JENIS KELAMIN (L/P)', 'KELAS', 'KETERANGAN', 'ALAMAT'],
            [1, '2026001', 'Alya Putri', '18/05/2019', 'P', 'A', 'Aktif', 'Sukoanyar'],
            [2, '2026002', 'Bagas Pratama', '2020-01-01', 'L', 'B', null, 'Wajak'],
        ]);

        $path = storage_path('app/testing-siswa-import-api.xlsx');
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'testing-siswa-import-api.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
