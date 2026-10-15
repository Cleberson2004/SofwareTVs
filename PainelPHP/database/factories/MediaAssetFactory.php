<?php

namespace Database\Factories;

use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaAsset>
 */
class MediaAssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'original_path' => 'media/originals/test.png',
            'original_width' => 7680,
            'original_height' => 1080,
            'mime_type' => 'image/png',
            'sha256' => str_repeat('a', 64),
            'processing_status' => 'inspected',
        ];
    }
}
