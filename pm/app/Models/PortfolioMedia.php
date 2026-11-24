<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphTo};

class PortfolioMedia extends Model
{
    protected $guarded = [];
    protected $table = 'portfolio_media';
    protected $casts = ['is_private' => 'boolean'];
    public function portfolio(): BelongsTo { return $this->belongsTo(Portfolio::class); }
}
