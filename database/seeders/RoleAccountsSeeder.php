<?php

namespace Database\Seeders;

use App\Models\OrangTuaSiswa;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoleAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@tk.local'],
            [
                'name' => 'Admin',
                'role' => 'admin',
                'password' => Hash::make('AdminTK2026!'),
            ]
        );

        $guru = User::updateOrCreate(
            ['email' => 'guru@tk.local'],
            [
                'name' => 'Guru',
                'role' => 'guru',
                'password' => Hash::make('GuruTK2026!'),
            ]
        );

        $ortu = User::updateOrCreate(
            ['phone' => '081234567890'],
            [
                'name' => 'Orang Tua Budi',
                'email' => '081234567890@ortu.local',
                'role' => 'orang_tua',
                'password' => Hash::make('1234'),
            ]
        );

        $budi = Siswa::where('nama', 'like', 'Budi%')->first();

        if ($budi) {
            OrangTuaSiswa::firstOrCreate([
                'user_id' => $ortu->id,
                'siswa_id' => $budi->id,
            ], [
                'hubungan' => 'Orang Tua',
            ]);
        }

        $this->command?->info('Akun admin, guru, dan orang tua berhasil dibuat/diperbarui.'.
            ($budi ? '' : ' PERINGATAN: siswa "Budi" tidak ditemukan, akun ortu belum tertaut ke siswa manapun.'));
    }
}
