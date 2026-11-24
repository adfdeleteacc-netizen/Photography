<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphTo};

class Campaign extends Model
{
    protected $guarded = [];
    protected $casts = ['audience' => 'array', 'scheduled_at' => 'datetime'];
}
