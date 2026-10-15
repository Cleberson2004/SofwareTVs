<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\CommandLeaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NextCommandController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, CommandLeaseService $leases): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        $command = $leases->leaseNext($device);

        if ($command === null) {
            return response()->json(status: 204);
        }

        return response()->json([
            'id' => $command->id,
            'type' => $command->type,
            'device_id' => $device->device_uuid,
            'idempotency_key' => $command->idempotency_key,
            'expires_at' => $command->expires_at?->toIso8601String(),
            'payload' => $command->payload,
        ]);
    }
}
