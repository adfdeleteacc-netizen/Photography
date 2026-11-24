<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphTo};

class GstReport extends Model
{
    protected $guarded = [];
    protected $casts = ['summary' => 'array'];
}
