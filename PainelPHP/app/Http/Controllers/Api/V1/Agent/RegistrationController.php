<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\AgentRegistrationRequest;
use App\Models\AuditLog;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class RegistrationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(AgentRegistrationRequest $request): JsonResponse
    {
        $token = Str::random(64);
        $data = $request->safe()->only([
            'name', 'agent_version', 'windows_version', 'gpu_name', 'driver_version', 'display_provider', 'simulation_mode',
        ]);

        $device = Device::query()->updateOrCreate(
            ['device_uuid' => $request->string('device_uuid')->toString()],
            [...$data, 'credential_hash' => hash('sha256', $token), 'status' => 'online', 'last_seen_at' => now(), 'registered_at' => now(), 'is_revoked' => false],
        );

        AuditLog::create([
            'device_id' => $device->id,
            'action' => 'device.registered',
            'auditable_type' => Device::class,
            'auditable_id' => (string) $device->id,
            'context' => ['display_provider' => $device->display_provider, 'simulation_mode' => $device->simulation_mode],
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['device_id' => $device->device_uuid, 'token' => $token], $device->wasRecentlyCreated ? 201 : 200);
    }
}
