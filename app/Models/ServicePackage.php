<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphTo};

class ServicePackage extends Model
{
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];
    public function owner(): MorphTo { return $this->morphTo(); }
    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
}
