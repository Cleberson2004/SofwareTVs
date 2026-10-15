<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\DisplayProfile;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'current_display_profile_id' => DisplayProfile::factory(),
            'name' => fake()->words(3, true),
            'device_uuid' => fake()->unique()->uuid(),
            'credential_hash' => hash('sha256', 'test-device-token'),
            'status' => 'offline',
            'display_provider' => 'Mock',
            'simulation_mode' => true,
            'is_revoked' => false,
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
        ];
    }
}
