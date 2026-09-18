<?php

namespace Tests\Feature\Api;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AbsensiApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_rejected_from_absensi_api(): void
    {
        $this->getJson('/api/absensi/monitor')->assertUnauthorized();
    }

    public function test_non_admin_role_is_forbidden(): void
    {
        $ortu = User::factory()->create(['role' => 'orang_tua']);

        $this->actingAs($ortu)
            ->getJson('/api/absensi/monitor')
            ->assertForbidden();
    }

    public function test_admin_can_scan_and_save_attendance_via_api(): void
    {
        Storage::fake('public');
        Carbon::setTestNow('2026-04-13 07:30:00');

        $admin = User::factory()->create(['role' => 'admin']);
        $kelas = Kelas::create(['nama_kelas' => 'TK A', 'tahun_ajaran' => '2025/2026']);
        $siswa = Siswa::create([
            'nis' => 'S-101',
            'nama' => 'Rani',
            'kelas_id' => $kelas->id,
            'jenis_kelamin' => 'P',
        ]);

        $this->actingAs($admin)
            ->postJson('/api/absensi/scan', ['qr_token' => $siswa->qr_token])
            ->assertOk()
            ->assertJson(['status' => 'ok', 'can_masuk' => true]);

        $this->actingAs($admin)
            ->postJson('/api/absensi/simpan', [
                'siswa_id' => $siswa->id,
                'foto' => $this->sampleBase64Png(),
                'jenis' => 'masuk',
            ])
            ->assertOk()
            ->assertJson(['status' => 'ok', 'jenis' => 'masuk']);

        $this->assertDatabaseHas('absensi', [
            'siswa_id' => $siswa->id,
            'tanggal' => '2026-04-13',
            'status' => 'hadir',
            'sumber' => 'scan_qr',
        ]);
    }

    public function test_saving_attendance_from_face_scan_records_scan_wajah_as_source(): void
    {
        Storage::fake('public');
        Carbon::setTestNow('2026-04-13 07:30:00');

        $admin = User::factory()->create(['role' => 'admin']);
        $kelas = Kelas::create(['nama_kelas' => 'TK A', 'tahun_ajaran' => '2025/2026']);
        $siswa = Siswa::create([
            'nis' => 'S-102',
            'nama' => 'Budi',
            'kelas_id' => $kelas->id,
            'jenis_kelamin' => 'L',
        ]);

        $this->actingAs($admin)
            ->postJson('/api/absensi/simpan', [
                'siswa_id' => $siswa->id,
                'foto' => $this->sampleBase64Png(),
                'jenis' => 'masuk',
                'sumber' => 'scan_wajah',
            ])
            ->assertOk()
            ->assertJson(['status' => 'ok', 'jenis' => 'masuk']);

        $this->assertDatabaseHas('absensi', [
            'siswa_id' => $siswa->id,
            'tanggal' => '2026-04-13',
            'status' => 'hadir',
            'sumber' => 'scan_wajah',
        ]);
    }

    public function test_admin_can_store_manual_attendance_via_api(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $kelas = Kelas::create(['nama_kelas' => 'TK B', 'tahun_ajaran' => '2025/2026']);
        $siswa = Siswa::create([
            'nis' => 'S-102',
            'nama' => 'Dimas',
            'kelas_id' => $kelas->id,
            'jenis_kelamin' => 'L',
        ]);

        $this->actingAs($admin)
            ->postJson('/api/absensi/manual', [
                'tanggal' => '2026-04-13',
                'siswa_id' => $siswa->id,
                'jenis' => 'masuk',
                'jam' => '07:00',
            ])
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('absensi', [
            'siswa_id' => $siswa->id,
            'sumber' => 'manual',
        ]);
    }

    public function test_admin_can_list_riwayat_and_update_status_via_api(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $kelas = Kelas::create(['nama_kelas' => 'TK C', 'tahun_ajaran' => '2025/2026']);
        $siswa = Siswa::create([
            'nis' => 'S-103',
            'nama' => 'Sari',
            'kelas_id' => $kelas->id,
            'jenis_kelamin' => 'P',
        ]);
        $absensi = Absensi::create([
            'siswa_id' => $siswa->id,
            'tanggal' => '2026-04-13',
            'jam_masuk' => '07:00:00',
            'status' => 'hadir',
            'sumber' => 'manual',
            'terlambat' => false,
        ]);

        $this->actingAs($admin)
            ->getJson('/api/absensi/riwayat?tanggal=2026-04-13')
            ->assertOk()
            ->assertJsonPath('summary.total', 1);

        $this->actingAs($admin)
            ->postJson("/api/absensi/{$absensi->id}/status", ['status' => 'izin'])
            ->assertOk();

        $this->assertDatabaseHas('absensi', ['id' => $absensi->id, 'status' => 'izin']);
    }

    public function test_admin_can_view_rekap_via_api(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Kelas::create(['nama_kelas' => 'TK D', 'tahun_ajaran' => '2025/2026']);

        $this->actingAs($admin)
            ->getJson('/api/absensi/rekap?tanggal=2026-04-13')
            ->assertOk()
            ->assertJsonStructure(['status', 'period_meta', 'class_reports', 'summary', 'trend']);
    }

    private function sampleBase64Png(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9pP6KyYAAAAASUVORK5CYII=';
    }
}
