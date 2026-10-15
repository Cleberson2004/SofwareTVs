<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Device;
use App\Models\MediaVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
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
            'media_variant_id' => MediaVariant::factory(),
            'name' => fake()->words(3, true),
            'status' => 'publishing',
        ];
    }
}
