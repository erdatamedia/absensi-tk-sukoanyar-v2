<?php

namespace Tests\Feature\Api;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\OrangTuaSiswa;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guru_can_access_absensi_and_view_siswa(): void
    {
        $guru = User::factory()->create(['role' => 'guru']);
        $kelas = Kelas::create(['nama_kelas' => 'TK A', 'tahun_ajaran' => '2025/2026']);
        Siswa::create(['nis' => 'S-600', 'nama' => 'Anak Guru', 'kelas_id' => $kelas->id, 'jenis_kelamin' => 'L']);

        $this->actingAs($guru)->getJson('/api/absensi/monitor')->assertOk();
        $this->actingAs($guru)->getJson('/api/siswa')->assertOk()->assertJsonFragment(['nis' => 'S-600']);
    }

    public function test_guru_cannot_write_siswa_or_access_kelas_and_settings(): void
    {
        $guru = User::factory()->create(['role' => 'guru']);
        $kelas = Kelas::create(['nama_kelas' => 'TK A', 'tahun_ajaran' => '2025/2026']);

        $this->actingAs($guru)->postJson('/api/siswa', [
            'nis' => 'S-601', 'nama' => 'Coba Tambah', 'kelas_id' => $kelas->id, 'jenis_kelamin' => 'L',
        ])->assertForbidden();

        $this->actingAs($guru)->getJson('/api/kelas')->assertForbidden();
        $this->actingAs($guru)->postJson('/api/kelas', ['nama_kelas' => 'TK C', 'tahun_ajaran' => '2025/2026'])->assertForbidden();
        $this->actingAs($guru)->getJson('/api/settings/school')->assertForbidden();
    }

    public function test_orang_tua_cannot_access_admin_or_guru_endpoints(): void
    {
        $ortu = User::factory()->create(['role' => 'orang_tua']);

        $this->actingAs($ortu)->getJson('/api/absensi/monitor')->assertForbidden();
        $this->actingAs($ortu)->getJson('/api/siswa')->assertForbidden();
        $this->actingAs($ortu)->getJson('/api/kelas')->assertForbidden();
    }

    public function test_parent_can_login_with_phone_and_pin(): void
    {
        $ortu = User::factory()->create([
            'role' => 'orang_tua',
            'phone' => '081234567890',
            'password' => '1234',
        ]);

        $this->postJson('/parent-login', [
            'phone' => '081234567890',
            'pin' => '1234',
        ])
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $this->assertAuthenticatedAs($ortu);
    }

    public function test_parent_login_fails_with_wrong_pin(): void
    {
        User::factory()->create([
            'role' => 'orang_tua',
            'phone' => '081234567890',
            'password' => '1234',
        ]);

        $this->postJson('/parent-login', [
            'phone' => '081234567890',
            'pin' => '9999',
        ])->assertStatus(422);

        $this->assertGuest();
    }

    public function test_parent_can_only_see_own_children_attendance(): void
    {
        $ortu = User::factory()->create(['role' => 'orang_tua', 'phone' => '081111111111', 'password' => '1111']);
        $kelas = Kelas::create(['nama_kelas' => 'TK A', 'tahun_ajaran' => '2025/2026']);
        $anak = Siswa::create(['nis' => 'S-700', 'nama' => 'Anak Sendiri', 'kelas_id' => $kelas->id, 'jenis_kelamin' => 'L']);
        $anakLain = Siswa::create(['nis' => 'S-701', 'nama' => 'Anak Orang Lain', 'kelas_id' => $kelas->id, 'jenis_kelamin' => 'P']);

        OrangTuaSiswa::create(['user_id' => $ortu->id, 'siswa_id' => $anak->id, 'hubungan' => 'Ibu']);

        Absensi::create(['siswa_id' => $anak->id, 'tanggal' => now()->toDateString(), 'jam_masuk' => '07:00:00', 'status' => 'hadir', 'sumber' => 'manual', 'terlambat' => false]);
        Absensi::create(['siswa_id' => $anakLain->id, 'tanggal' => now()->toDateString(), 'jam_masuk' => '07:05:00', 'status' => 'hadir', 'sumber' => 'manual', 'terlambat' => false]);

        $response = $this->actingAs($ortu)->getJson('/api/portal-ortu/absensi-anak')->assertOk();

        $names = collect($response->json('absensi.data'))->pluck('siswa.nama')->all();
        $this->assertContains('Anak Sendiri', $names);
        $this->assertNotContains('Anak Orang Lain', $names);
    }
}
