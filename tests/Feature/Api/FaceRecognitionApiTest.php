<?php

namespace Tests\Feature\Api;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FaceRecognitionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_enroll_face_photo_for_siswa(): void
    {
        Storage::fake('public');
        Http::fake([
            '*/embed' => Http::response([
                'embedding' => array_fill(0, 128, 0.1),
                'confidence' => 0.95,
            ]),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $kelas = Kelas::create(['nama_kelas' => 'TK A', 'tahun_ajaran' => '2025/2026']);
        $siswa = Siswa::create(['nis' => 'S-500', 'nama' => 'Wajah Test', 'kelas_id' => $kelas->id, 'jenis_kelamin' => 'L']);

        $this->actingAs($admin)
            ->postJson("/api/siswa/{$siswa->id}/foto-referensi", [
                'foto' => UploadedFile::fake()->image('wajah.jpg', 300, 300),
            ])
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $siswa->refresh();
        $this->assertNotNull($siswa->foto_referensi);
        Storage::disk('public')->assertExists($siswa->foto_referensi);
        $this->assertCount(128, $siswa->face_embedding);
    }

    public function test_enrollment_fails_when_no_face_detected(): void
    {
        Storage::fake('public');
        Http::fake([
            '*/embed' => Http::response(['detail' => 'Wajah tidak terdeteksi pada foto.'], 422),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $kelas = Kelas::create(['nama_kelas' => 'TK A', 'tahun_ajaran' => '2025/2026']);
        $siswa = Siswa::create(['nis' => 'S-501', 'nama' => 'Tanpa Wajah', 'kelas_id' => $kelas->id, 'jenis_kelamin' => 'P']);

        $this->actingAs($admin)
            ->postJson("/api/siswa/{$siswa->id}/foto-referensi", [
                'foto' => UploadedFile::fake()->image('blank.jpg', 300, 300),
            ])
            ->assertStatus(422);

        $siswa->refresh();
        $this->assertNull($siswa->foto_referensi);
        $this->assertNull($siswa->face_embedding);
    }

    public function test_recognize_face_returns_match_and_scan_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $kelas = Kelas::create(['nama_kelas' => 'TK A', 'tahun_ajaran' => '2025/2026']);
        $siswa = Siswa::create([
            'nis' => 'S-502',
            'nama' => 'Sudah Terdaftar',
            'kelas_id' => $kelas->id,
            'jenis_kelamin' => 'L',
            'face_embedding' => array_fill(0, 128, 0.2),
        ]);

        Http::fake([
            '*/recognize' => Http::response(['siswa_id' => $siswa->id, 'score' => 0.62]),
        ]);

        $this->actingAs($admin)
            ->postJson('/api/absensi/recognize-face', ['foto' => base64_encode('fake-jpeg-bytes')])
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'siswa_id' => $siswa->id,
                'nama' => $siswa->nama,
                'can_masuk' => true,
                'can_pulang' => false,
                'score' => 0.62,
            ]);
    }

    public function test_recognize_face_returns_no_match(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $kelas = Kelas::create(['nama_kelas' => 'TK A', 'tahun_ajaran' => '2025/2026']);
        Siswa::create([
            'nis' => 'S-503',
            'nama' => 'Terdaftar Lain',
            'kelas_id' => $kelas->id,
            'jenis_kelamin' => 'P',
            'face_embedding' => array_fill(0, 128, 0.3),
        ]);

        Http::fake([
            '*/recognize' => Http::response(['siswa_id' => null, 'score' => 0.1]),
        ]);

        $this->actingAs($admin)
            ->postJson('/api/absensi/recognize-face', ['foto' => base64_encode('fake-jpeg-bytes')])
            ->assertOk()
            ->assertJson(['status' => 'no_match']);
    }

    public function test_recognize_face_with_no_enrolled_students_returns_no_match_without_calling_service(): void
    {
        Http::fake();

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->postJson('/api/absensi/recognize-face', ['foto' => base64_encode('fake-jpeg-bytes')])
            ->assertOk()
            ->assertJson(['status' => 'no_match']);

        Http::assertNothingSent();
    }

    public function test_recognize_face_reports_service_unavailable_when_service_down(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $kelas = Kelas::create(['nama_kelas' => 'TK A', 'tahun_ajaran' => '2025/2026']);
        Siswa::create([
            'nis' => 'S-504',
            'nama' => 'Ada Wajah',
            'kelas_id' => $kelas->id,
            'jenis_kelamin' => 'L',
            'face_embedding' => array_fill(0, 128, 0.4),
        ]);

        Http::fake(function () {
            throw new ConnectionException('Connection refused');
        });

        $this->actingAs($admin)
            ->postJson('/api/absensi/recognize-face', ['foto' => base64_encode('fake-jpeg-bytes')])
            ->assertOk()
            ->assertJson(['status' => 'service_unavailable']);
    }
}
