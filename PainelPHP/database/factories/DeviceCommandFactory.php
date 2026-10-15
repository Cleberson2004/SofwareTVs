<?php

namespace Database\Factories;

use App\Enums\CommandStatus;
use App\Models\Device;
use App\Models\DeviceCommand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceCommand>
 */
class DeviceCommandFactory extends Factory
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
            'type' => 'vlc.status',
            'payload' => [],
            'status' => CommandStatus::Queued,
            'priority' => 5,
            'idempotency_key' => fake()->unique()->uuid(),
            'expires_at' => now()->addHour(),
        ];
    }
}
