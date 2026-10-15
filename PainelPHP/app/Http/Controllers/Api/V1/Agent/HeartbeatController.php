<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\AgentHeartbeatRequest;
use App\Models\Device;
use App\Models\DeviceHeartbeat;
use Illuminate\Http\JsonResponse;

class HeartbeatController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(AgentHeartbeatRequest $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        $data = $request->validated();

        $device->update([
            'status' => 'online',
            'last_seen_at' => now(),
            'agent_version' => $data['agent_version'],
            'windows_version' => $data['windows_version'] ?? null,
            'gpu_name' => $data['gpu_name'] ?? null,
            'driver_version' => $data['driver_version'] ?? null,
            'display_provider' => $data['display_provider'],
            'simulation_mode' => $data['simulation_mode'],
        ]);

        DeviceHeartbeat::create([
            ...$data,
            'device_id' => $device->id,
            'received_at' => now(),
        ]);

        return response()->json(['received_at' => now()->toIso8601String()]);
    }
}
