<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublishCampaignRequest;
use App\Models\Device;
use App\Models\MediaVariant;
use App\Services\CampaignPublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class CampaignPublicationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(PublishCampaignRequest $request, MediaVariant $mediaVariant, CampaignPublisher $publisher): RedirectResponse
    {
        if ($mediaVariant->approved_at === null) {
            throw ValidationException::withMessages(['campaign' => 'A variante precisa ser aprovada antes da publicação.']);
        }

        $device = Device::findOrFail($request->integer('device_id'));

        if ($device->current_display_profile_id !== $mediaVariant->display_profile_id) {
            throw ValidationException::withMessages(['device_id' => 'O perfil atual do dispositivo não corresponde à variante aprovada.']);
        }

        $realWidth = data_get($device->display_state, 'total_width');
        $realHeight = data_get($device->display_state, 'total_height');

        if (($realWidth !== null && (int) $realWidth !== $mediaVariant->width) || ($realHeight !== null && (int) $realHeight !== $mediaVariant->height)) {
            throw ValidationException::withMessages(['device_id' => 'A resolução real informada pelo agente diverge da variante.']);
        }

        $publisher->publish(
            $mediaVariant,
            $device,
            $request->string('name')->toString(),
            $request->user()?->id,
            $request->ip(),
        );

        return back()->with('success', 'Campanha enfileirada para o dispositivo.');
    }
}
