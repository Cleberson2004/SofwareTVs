<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CommandStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeviceCommandRequest;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\DeviceCommand;
use Illuminate\Http\JsonResponse;

class DeviceCommandController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Device $device): JsonResponse
    {
        return response()->json($device->commands()->latest()->paginate(50));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDeviceCommandRequest $request, Device $device): JsonResponse
    {
        $command = $device->commands()->firstOrCreate(
            ['idempotency_key' => $request->string('idempotency_key')->toString()],
            [
                'type' => $request->string('type')->toString(),
                'payload' => $request->validated('payload') ?? [],
                'status' => CommandStatus::Queued,
                'priority' => $request->integer('priority', 5),
                'requested_by' => $request->user()?->id,
                'expires_at' => $request->date('expires_at'),
            ],
        );

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'device_id' => $device->id,
            'action' => 'command.created',
            'auditable_type' => DeviceCommand::class,
            'auditable_id' => $command->id,
            'context' => ['type' => $command->type, 'duplicate' => ! $command->wasRecentlyCreated],
            'ip_address' => $request->ip(),
        ]);

        return response()->json($command, $command->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Device $device, DeviceCommand $deviceCommand): JsonResponse
    {
        abort_unless($deviceCommand->device_id === $device->id, 404);

        return response()->json($deviceCommand);
    }
}
