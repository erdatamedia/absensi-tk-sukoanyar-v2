<?php

namespace Tests\Feature\Api;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppSettingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_and_update_school_settings_via_api(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->getJson('/api/settings/school')
            ->assertOk()
            ->assertJsonStructure(['status', 'settings' => ['school_name', 'operational_start', 'operational_end']]);

        $this->actingAs($admin)
            ->postJson('/api/settings/school', [
                'school_name' => 'TK PGRI 2 Wajak',
                'school_tagline' => 'Absensi Harian Sekolah',
                'operational_start' => '06:30',
                'operational_end' => '13:00',
                'school_logo' => UploadedFile::fake()->image('logo.png', 256, 256),
            ])
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('app_settings', ['key' => 'school_name', 'value' => 'TK PGRI 2 Wajak']);
        $logoPath = AppSetting::where('key', 'school_logo_path')->value('value');
        Storage::disk('public')->assertExists($logoPath);
    }

    public function test_branding_endpoint_is_public_and_reflects_saved_settings(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->postJson('/api/settings/school', [
            'school_name' => 'TK Ceria',
            'operational_start' => '06:30',
            'operational_end' => '13:00',
            'school_logo' => UploadedFile::fake()->image('logo.png', 256, 256),
        ])->assertOk();

        $this->getJson('/api/settings/branding')
            ->assertOk()
            ->assertJson(['status' => 'ok', 'school_name' => 'TK Ceria'])
            ->assertJsonStructure(['school_logo_url']);
    }
}
