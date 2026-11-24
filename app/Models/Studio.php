<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphMany};

class Studio extends Model
{
    protected $guarded = [];
    protected $casts = ['is_verified' => 'boolean', 'is_featured' => 'boolean'];

    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function members(): HasMany { return $this->hasMany(StudioMember::class); }
    public function photographers(): HasMany { return $this->hasMany(PhotographerProfile::class); }
    public function packages(): MorphMany { return $this->morphMany(ServicePackage::class, 'owner'); }
    public function portfolios(): MorphMany { return $this->morphMany(Portfolio::class, 'owner'); }
}
