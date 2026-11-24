<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphTo};

class Referral extends Model
{
    protected $guarded = [];
    protected $casts = ['rewarded_at' => 'datetime'];
}
