<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphTo};

class Message extends Model
{
    protected $guarded = [];
    protected $casts = ['read_at' => 'datetime'];
    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'sender_id'); }
}
