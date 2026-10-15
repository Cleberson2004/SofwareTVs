<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\MediaVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaDownloadController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, MediaVariant $mediaVariant): StreamedResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        abort_if($mediaVariant->approved_at === null, 403, 'Variante não aprovada.');
        abort_if($device->current_display_profile_id !== $mediaVariant->display_profile_id, 403, 'Variante incompatível com o dispositivo.');
        abort_unless(Storage::disk('local')->exists($mediaVariant->path), 404);

        return Storage::disk('local')->download($mediaVariant->path, 'campaign.png', [
            'Content-Type' => 'image/png',
            'X-Content-SHA256' => $mediaVariant->sha256,
        ]);
    }
}
