<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphTo};

class LoyaltyWallet extends Model
{
    protected $guarded = [];
    public function transactions(): HasMany { return $this->hasMany(LoyaltyTransaction::class); }
}
