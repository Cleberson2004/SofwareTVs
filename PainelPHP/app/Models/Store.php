<?php

namespace App\Models;

use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code'])]
class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory;

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }
}
