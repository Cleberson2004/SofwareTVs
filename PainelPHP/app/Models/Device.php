<?php

namespace App\Models;

use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['store_id', 'current_display_profile_id', 'name', 'device_uuid', 'credential_hash', 'status', 'last_seen_at', 'agent_version', 'windows_version', 'gpu_name', 'driver_version', 'display_provider', 'simulation_mode', 'is_revoked', 'registered_at', 'display_state'])]
#[Hidden(['credential_hash'])]
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'registered_at' => 'datetime',
            'simulation_mode' => 'boolean',
            'is_revoked' => 'boolean',
            'display_state' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function displayProfile(): BelongsTo
    {
        return $this->belongsTo(DisplayProfile::class, 'current_display_profile_id');
    }

    public function commands(): HasMany
    {
        return $this->hasMany(DeviceCommand::class);
    }

    public function heartbeats(): HasMany
    {
        return $this->hasMany(DeviceHeartbeat::class);
    }
}
