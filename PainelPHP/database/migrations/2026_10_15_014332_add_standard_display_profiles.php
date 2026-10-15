<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ([
            ['name' => '2 TVs horizontais — 1 × 2', 'rows' => 1, 'columns' => 2, 'total_width' => 3840, 'total_height' => 1080],
            ['name' => '3 TVs horizontais — 1 × 3', 'rows' => 1, 'columns' => 3, 'total_width' => 5760, 'total_height' => 1080],
            ['name' => '4 TVs horizontais — 2 × 2', 'rows' => 2, 'columns' => 2, 'total_width' => 3840, 'total_height' => 2160],
        ] as $profile) {
            DB::table('display_profiles')->updateOrInsert(
                [
                    'rows' => $profile['rows'],
                    'columns' => $profile['columns'],
                    'display_width' => 1920,
                    'display_height' => 1080,
                    'overlap_x' => 0,
                    'overlap_y' => 0,
                ],
                [
                    ...$profile,
                    'orientation' => 'landscape',
                    'refresh_rate' => 60,
                    'is_tested' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        // Perfis podem estar associados a dispositivos; preservá-los evita perda de configuração.
    }
};
