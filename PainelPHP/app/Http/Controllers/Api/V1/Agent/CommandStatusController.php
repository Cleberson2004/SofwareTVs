<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Enums\CommandStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteDeviceCommandRequest;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Device;
use App\Models\DeviceCommand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommandStatusController extends Controller
{
    public function started(Request $request, DeviceCommand $deviceCommand): JsonResponse
    {
        $this->assertOwnership($request, $deviceCommand);

        if ($deviceCommand->status === CommandStatus::Running || $deviceCommand->status === CommandStatus::Succeeded) {
            return response()->json($deviceCommand);
        }

        abort_unless($deviceCommand->status === CommandStatus::Leased && $deviceCommand->lease_expires_at?->isFuture(), 409, 'O comando não possui um lease válido.');
        $deviceCommand->update(['status' => CommandStatus::Running, 'started_at' => now(), 'lease_expires_at' => null]);

        return response()->json($deviceCommand->fresh());
    }

    public function completed(CompleteDeviceCommandRequest $request, DeviceCommand $deviceCommand): JsonResponse
    {
        $this->assertOwnership($request, $deviceCommand);

        if ($deviceCommand->status === CommandStatus::Succeeded) {
            return response()->json($deviceCommand);
        }

        abort_unless($deviceCommand->status === CommandStatus::Running, 409, 'O comando não está em execução.');
        $deviceCommand->update([
            'status' => CommandStatus::Succeeded,
            'finished_at' => now(),
            'result' => $request->validated('result') ?? [],
            'error_code' => null,
            'error_message' => null,
        ]);

        if ($deviceCommand->type === 'campaign.download') {
            Campaign::query()->whereKey(data_get($deviceCommand->payload, 'campaign_id'))->update(['status' => 'active', 'published_at' => now()]);
        }

        $this->audit($request, $deviceCommand, 'command.succeeded');

        return response()->json($deviceCommand->fresh());
    }

    public function failed(CompleteDeviceCommandRequest $request, DeviceCommand $deviceCommand): JsonResponse
    {
        $this->assertOwnership($request, $deviceCommand);
        abort_unless(in_array($deviceCommand->status, [CommandStatus::Running, CommandStatus::Leased], true), 409, 'O comando não está em execução.');

        $deviceCommand->update([
            'status' => CommandStatus::Failed,
            'finished_at' => now(),
            'result' => $request->validated('result') ?? [],
            'error_code' => $request->validated('error_code') ?? 'agent_error',
            'error_message' => $request->validated('error_message') ?? 'Falha não detalhada pelo agente.',
            'lease_expires_at' => null,
        ]);
        $this->audit($request, $deviceCommand, 'command.failed');

        return response()->json($deviceCommand->fresh());
    }

    private function assertOwnership(Request $request, DeviceCommand $deviceCommand): void
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        abort_unless($deviceCommand->device_id === $device->id, 404);
    }

    private function audit(Request $request, DeviceCommand $command, string $action): void
    {
        AuditLog::create([
            'device_id' => $command->device_id,
            'action' => $action,
            'auditable_type' => DeviceCommand::class,
            'auditable_id' => $command->id,
            'context' => ['type' => $command->type, 'error_code' => $command->error_code],
            'ip_address' => $request->ip(),
        ]);
    }
}
