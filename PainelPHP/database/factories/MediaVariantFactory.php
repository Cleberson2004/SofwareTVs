<?php

namespace Database\Factories;

use App\Models\DisplayProfile;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaVariant>
 */
class MediaVariantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'media_asset_id' => MediaAsset::factory(),
            'display_profile_id' => DisplayProfile::factory(),
            'variant_type' => 'normalized',
            'path' => 'media/normalized/test.png',
            'preview_path' => 'media/previews/test.jpg',
            'width' => 7680,
            'height' => 1080,
            'adjustment_mode' => 'EDGE_ADJUST',
            'processing_details' => [],
            'sha256' => str_repeat('b', 64),
        ];
    }
}
