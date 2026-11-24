<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphTo};

class Portfolio extends Model
{
    protected $guarded = [];
    protected $casts = ['is_featured' => 'boolean', 'is_published' => 'boolean'];
    public function owner(): MorphTo { return $this->morphTo(); }
    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function media(): HasMany { return $this->hasMany(PortfolioMedia::class)->orderBy('sort_order'); }
}
