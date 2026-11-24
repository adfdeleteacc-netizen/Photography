<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphTo};

class Payment extends Model
{
    protected $guarded = [];
    protected $casts = ['gateway_payload' => 'array'];
    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
}
