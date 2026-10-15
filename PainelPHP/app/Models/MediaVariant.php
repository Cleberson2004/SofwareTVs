<?php

namespace App\Models;

use Database\Factories\MediaVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['media_asset_id', 'display_profile_id', 'variant_type', 'path', 'preview_path', 'width', 'height', 'adjustment_mode', 'processing_details', 'sha256', 'approved_at', 'approved_by'])]
class MediaVariant extends Model
{
    /** @use HasFactory<MediaVariantFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'processing_details' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }

    public function displayProfile(): BelongsTo
    {
        return $this->belongsTo(DisplayProfile::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }
}
