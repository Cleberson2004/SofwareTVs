<?php

namespace App\Models;

use Database\Factories\DeviceHeartbeatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['device_id', 'received_at', 'vlc_status', 'mosaic_status', 'desktop_width', 'desktop_height', 'screens_detected', 'cpu_usage', 'memory_usage', 'free_disk_space', 'last_command_id', 'display_provider', 'simulation_mode', 'diagnostics'])]
class DeviceHeartbeat extends Model
{
    /** @use HasFactory<DeviceHeartbeatFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'vlc_status' => 'array',
            'mosaic_status' => 'array',
            'simulation_mode' => 'boolean',
            'diagnostics' => 'array',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
