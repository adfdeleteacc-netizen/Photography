<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphTo};

class Coupon extends Model
{
    protected $guarded = [];
    protected $casts = ['starts_at' => 'datetime', 'expires_at' => 'datetime', 'is_active' => 'boolean'];
    public function usages(): HasMany { return $this->hasMany(CouponUsage::class); }
}
