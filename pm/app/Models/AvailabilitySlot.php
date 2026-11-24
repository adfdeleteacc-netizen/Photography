<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphTo};

class AvailabilitySlot extends Model
{
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];
}
