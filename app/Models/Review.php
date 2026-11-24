<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphTo};

class Review extends Model
{
    protected $guarded = [];
    protected $casts = ['media' => 'array', 'is_visible' => 'boolean'];
    public function customer(): BelongsTo { return $this->belongsTo(User::class, 'customer_id'); }
    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
}
