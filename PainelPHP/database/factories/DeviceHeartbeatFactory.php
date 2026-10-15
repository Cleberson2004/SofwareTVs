<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\DeviceHeartbeat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceHeartbeat>
 */
class DeviceHeartbeatFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => Device::factory(),
            'received_at' => now(),
            'vlc_status' => ['running' => true, 'pid' => 1234],
            'mosaic_status' => ['enabled' => true],
            'desktop_width' => 7680,
            'desktop_height' => 1080,
            'screens_detected' => 4,
            'display_provider' => 'Mock',
            'simulation_mode' => true,
        ];
    }
}
