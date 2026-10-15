<?php

namespace Database\Factories;

use App\Models\DisplayProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DisplayProfile>
 */
class DisplayProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Videowall 1x4 horizontal',
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
        ];
    }
}
