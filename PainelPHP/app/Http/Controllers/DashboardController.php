<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\DisplayProfile;
use App\Models\MediaAsset;
use App\Services\MediaProcessor;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(MediaProcessor $processor): Response
    {
        $profiles = DisplayProfile::query()->orderBy('name')->get();
        $devices = Device::query()->with('displayProfile')->orderBy('name')->get();
        $assets = MediaAsset::query()
            ->whereDoesntHave('variants', fn ($query) => $query->where('variant_type', 'screen_composition'))
            ->with(['variants' => fn ($query) => $query->with('displayProfile')->latest()])
            ->latest()
            ->get();

        return Inertia::render('Dashboard', [
            'profiles' => $profiles,
            'devices' => $devices,
            'assets' => $assets->map(function (MediaAsset $asset) use ($profiles, $processor): array {
                $profile = $profiles->first();

                return [
                    ...$asset->toArray(),
                    'classifications' => $profile === null ? null : [
                        'screen' => $processor->classify($asset, $profile, 'screen'),
                        'wall' => $processor->classify($asset, $profile, 'wall'),
                    ],
                    'original_url' => route('media.preview', ['mediaAsset' => $asset, 'source' => 'original']),
                    'variants' => $asset->variants->map(fn ($variant): array => [
                        ...$variant->toArray(),
                        'preview_url' => route('media.preview', ['mediaAsset' => $asset, 'variant' => $variant->id]),
                    ]),
                ];
            }),
            'commands' => DeviceCommand::query()->with('device')->latest()->limit(20)->get(),
            'allowedCommands' => config('tv_agent.allowed_commands'),
            'routes' => ['dashboard' => Route::has('dashboard') ? route('dashboard') : '/'],
        ]);
    }
}
