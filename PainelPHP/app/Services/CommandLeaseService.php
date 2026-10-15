<?php

namespace App\Services;

use App\Enums\CommandStatus;
use App\Models\Device;
use App\Models\DeviceCommand;
use Illuminate\Support\Facades\DB;

class CommandLeaseService
{
    public function leaseNext(Device $device): ?DeviceCommand
    {
        return DB::transaction(function () use ($device): ?DeviceCommand {
            DeviceCommand::query()
                ->whereBelongsTo($device)
                ->where('status', CommandStatus::Leased)
                ->where('lease_expires_at', '<=', now())
                ->update(['status' => CommandStatus::Queued, 'lease_expires_at' => null]);

            DeviceCommand::query()
                ->whereBelongsTo($device)
                ->where('status', CommandStatus::Queued)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->update(['status' => CommandStatus::Expired, 'finished_at' => now()]);

            $command = DeviceCommand::query()
                ->whereBelongsTo($device)
                ->where('status', CommandStatus::Queued)
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->orderByDesc('priority')
                ->oldest()
                ->lockForUpdate()
                ->first();

            if ($command === null) {
                return null;
            }

            $command->update([
                'status' => CommandStatus::Leased,
                'lease_expires_at' => now()->addSeconds(config('tv_agent.command_lease_seconds')),
            ]);

            return $command->fresh();
        });
    }
}
