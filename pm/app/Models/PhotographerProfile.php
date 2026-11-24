<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphMany};

class PhotographerProfile extends Model
{
    protected $guarded = [];
    protected $casts = ['is_verified' => 'boolean', 'is_featured' => 'boolean', 'rating_avg' => 'float'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function studio(): BelongsTo { return $this->belongsTo(Studio::class); }
    public function categories(): BelongsToMany { return $this->belongsToMany(Category::class, 'category_photographer'); }
    public function packages(): MorphMany { return $this->morphMany(ServicePackage::class, 'owner'); }
    public function portfolios(): MorphMany { return $this->morphMany(Portfolio::class, 'owner'); }
    public function availabilitySlots(): HasMany { return $this->hasMany(AvailabilitySlot::class); }
    public function blockedDates(): HasMany { return $this->hasMany(BlockedDate::class); }
    public function bookings(): HasMany { return $this->hasMany(Booking::class); }
    public function reviews(): HasMany { return $this->hasMany(Review::class); }
}
