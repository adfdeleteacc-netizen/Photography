<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphTo};

class SubscriptionPlan extends Model
{
    protected $guarded = [];
    protected $casts = ['featured_listing' => 'boolean', 'analytics_access' => 'boolean', 'for_studios' => 'boolean', 'is_active' => 'boolean'];
}
