<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphTo};

class KycDocument extends Model
{
    protected $guarded = [];
    protected $casts = ['reviewed_at' => 'datetime'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
