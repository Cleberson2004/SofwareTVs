<?php

namespace App\Services;

use App\Models\DisplayProfile;

class DisplayProfileResolver
{
    /** @param array<string, mixed> $layout */
    public function resolve(int $fallbackProfileId, array $layout): DisplayProfile
    {
        if (! isset($layout['tv_count'], $layout['orientation'])) {
            return DisplayProfile::findOrFail($fallbackProfileId);
        }

        $count = max(1, min(64, (int) $layout['tv_count']));
        $orientation = $layout['orientation'] === 'portrait' ? 'portrait' : 'landscape';
        $rows = max(1, min(16, (int) ($layout['rows'] ?? 1)));
        $columns = max(1, min(16, (int) ($layout['columns'] ?? $count)));

        if ($rows * $columns !== $count) {
            $rows = 1;
            $columns = $count;
        }

        [$displayWidth, $displayHeight] = $orientation === 'portrait'
            ? [1080, 1920]
            : [1920, 1080];

        return DisplayProfile::query()->firstOrCreate(
            [
                'rows' => $rows,
                'columns' => $columns,
                'display_width' => $displayWidth,
                'display_height' => $displayHeight,
                'overlap_x' => 0,
                'overlap_y' => 0,
            ],
            [
                'name' => $count.' TV'.($count === 1 ? '' : 's').' em '.($orientation === 'portrait' ? 'retrato' : 'paisagem').' — '.$rows.' × '.$columns,
                'orientation' => $orientation,
                'total_width' => $displayWidth * $columns,
                'total_height' => $displayHeight * $rows,
                'refresh_rate' => 60,
                'is_tested' => false,
            ],
        );
    }
}
