<?php

namespace App\Models;

use Database\Factories\DisplayProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'rows', 'columns', 'display_width', 'display_height', 'orientation', 'total_width', 'total_height', 'refresh_rate', 'overlap_x', 'overlap_y', 'nvapi_payload', 'is_tested', 'tested_at'])]
class DisplayProfile extends Model
{
    /** @use HasFactory<DisplayProfileFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'refresh_rate' => 'float',
            'nvapi_payload' => 'array',
            'is_tested' => 'boolean',
            'tested_at' => 'datetime',
        ];
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class, 'current_display_profile_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(MediaVariant::class);
    }
}
