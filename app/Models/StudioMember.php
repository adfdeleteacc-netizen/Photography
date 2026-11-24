<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphTo};

class StudioMember extends Model
{
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];
    public function studio(): BelongsTo { return $this->belongsTo(Studio::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
