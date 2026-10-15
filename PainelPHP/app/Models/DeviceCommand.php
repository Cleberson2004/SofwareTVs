<?php

namespace App\Models;

use App\Enums\CommandStatus;
use Database\Factories\DeviceCommandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id', 'device_id', 'type', 'payload', 'status', 'priority', 'idempotency_key', 'lease_expires_at', 'requested_by', 'started_at', 'finished_at', 'expires_at', 'result', 'error_code', 'error_message'])]
class DeviceCommand extends Model
{
    /** @use HasFactory<DeviceCommandFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'result' => 'array',
            'status' => CommandStatus::class,
            'lease_expires_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
