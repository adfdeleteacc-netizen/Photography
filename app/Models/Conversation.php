<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphTo};

class Conversation extends Model
{
    protected $guarded = [];
    protected $casts = ['last_message_at' => 'datetime'];
    public function messages(): HasMany { return $this->hasMany(Message::class); }
    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
}
