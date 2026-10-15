<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\AgentDisplayStateRequest;
use App\Models\Device;
use Illuminate\Http\JsonResponse;

class DisplayStateController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(AgentDisplayStateRequest $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        $device->update(['display_state' => $request->validated(), 'last_seen_at' => now(), 'status' => 'online']);

        return response()->json(['display_state' => $device->display_state]);
    }
}
