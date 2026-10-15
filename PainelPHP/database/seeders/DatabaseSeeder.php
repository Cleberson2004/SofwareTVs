<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\DisplayProfile;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Administrador',
            'email' => config('tv_agent.admin_email'),
            'password' => config('tv_agent.admin_password'),
        ]);

        $store = Store::create(['name' => 'Loja demonstração', 'code' => 'LOJA-DEMO']);
        $profile = DisplayProfile::create([
            'name' => '4 TVs horizontais — 1 × 4',
            'rows' => 1,
            'columns' => 4,
            'display_width' => 1920,
            'display_height' => 1080,
            'orientation' => 'horizontal',
            'total_width' => 7680,
            'total_height' => 1080,
            'refresh_rate' => 60,
            'overlap_x' => 0,
            'overlap_y' => 0,
            'is_tested' => true,
            'tested_at' => now(),
        ]);

        Device::create([
            'store_id' => $store->id,
            'current_display_profile_id' => $profile->id,
            'name' => 'Player Loja Demo',
            'device_uuid' => '00000000-0000-4000-8000-000000000001',
            'status' => 'offline',
            'display_provider' => 'Mock',
            'simulation_mode' => true,
            'display_state' => [
                'enabled' => true,
                'rows' => 1,
                'columns' => 4,
                'display_width' => 1920,
                'display_height' => 1080,
                'total_width' => 7680,
                'total_height' => 1080,
                'refresh_rate' => 60,
                'overlap_x' => 0,
                'overlap_y' => 0,
                'displays' => [],
            ],
        ]);
    }
}
