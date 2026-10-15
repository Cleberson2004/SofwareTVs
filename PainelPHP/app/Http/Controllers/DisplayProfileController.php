<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDisplayProfileRequest;
use App\Models\DisplayProfile;
use Illuminate\Http\RedirectResponse;

class DisplayProfileController extends Controller
{
    public function store(StoreDisplayProfileRequest $request): RedirectResponse
    {
        $count = $request->integer('display_count');
        $orientation = $request->string('orientation')->toString();
        [$displayWidth, $displayHeight] = $orientation === 'portrait'
            ? [1080, 1920]
            : [1920, 1080];

        $profile = DisplayProfile::query()->firstOrCreate(
            [
                'rows' => 1,
                'columns' => $count,
                'display_width' => $displayWidth,
                'display_height' => $displayHeight,
                'overlap_x' => 0,
                'overlap_y' => 0,
            ],
            [
                'name' => $count.' TV'.($count === 1 ? '' : 's').' em '.($orientation === 'portrait' ? 'retrato' : 'paisagem').' — 1 × '.$count,
                'orientation' => $orientation,
                'total_width' => $displayWidth * $count,
                'total_height' => $displayHeight,
                'refresh_rate' => 60,
                'is_tested' => false,
            ],
        );

        return back()
            ->with('success', "Perfil configurado: {$profile->name} ({$profile->total_width} × {$profile->total_height}).")
            ->with('profile_id', $profile->id);
    }
}
