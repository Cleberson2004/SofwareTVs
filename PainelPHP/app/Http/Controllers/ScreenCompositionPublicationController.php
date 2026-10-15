<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublishScreenCompositionRequest;
use App\Models\Device;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Services\CampaignPublisher;
use App\Services\DisplayProfileResolver;
use App\Services\MediaProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ScreenCompositionPublicationController extends Controller
{
    public function __invoke(
        PublishScreenCompositionRequest $request,
        MediaProcessor $processor,
        CampaignPublisher $publisher,
        DisplayProfileResolver $profileResolver,
    ): RedirectResponse {
        $profile = $profileResolver->resolve($request->integer('display_profile_id'), $request->validated());
        $device = Device::findOrFail($request->integer('device_id'));
        $placements = collect($request->validated('placements'));
        $screenCount = $profile->rows * $profile->columns;

        if ($placements->contains(fn (array $placement): bool => $placement['screen'] > $screenCount)) {
            throw ValidationException::withMessages(['placements' => 'Uma das telas selecionadas não existe neste perfil.']);
        }

        if ($device->current_display_profile_id !== $profile->id) {
            throw ValidationException::withMessages(['device_id' => 'O perfil atual do dispositivo não corresponde à composição.']);
        }

        $realWidth = data_get($device->display_state, 'total_width');
        $realHeight = data_get($device->display_state, 'total_height');

        if (($realWidth !== null && (int) $realWidth !== $profile->total_width) || ($realHeight !== null && (int) $realHeight !== $profile->total_height)) {
            throw ValidationException::withMessages(['device_id' => 'A resolução real informada pelo agente diverge da composição.']);
        }

        $assets = MediaAsset::query()
            ->whereKey($placements->pluck('asset_id'))
            ->get()
            ->keyBy('id');
        $assetByScreen = $placements->mapWithKeys(
            fn (array $placement): array => [$placement['screen'] => $assets->get($placement['asset_id'])],
        )->all();
        $variants = MediaVariant::query()
            ->whereKey($placements->pluck('variant_id')->filter())
            ->get()
            ->keyBy('id');
        $variantByScreen = [];

        foreach ($placements as $placement) {
            $variantId = $placement['variant_id'] ?? null;
            if ($variantId === null) {
                continue;
            }

            $variant = $variants->get($variantId);
            if ($variant === null
                || $variant->media_asset_id !== $placement['asset_id']
                || $variant->variant_type !== 'screen'
                || $variant->width !== $profile->display_width
                || $variant->height !== $profile->display_height) {
                throw ValidationException::withMessages(['placements' => 'Uma correção selecionada não corresponde à imagem ou à resolução da TV.']);
            }

            $variantByScreen[$placement['screen']] = $variant;
        }

        $variant = $processor->composeForScreens(
            $profile,
            $assetByScreen,
            $request->string('name')->toString(),
            $request->user()?->id,
            $variantByScreen,
        );

        $publisher->publish(
            $variant,
            $device,
            $request->string('name')->toString(),
            $request->user()?->id,
            $request->ip(),
        );

        return back()->with('success', 'Composição criada e enfileirada para o dispositivo.');
    }
}
