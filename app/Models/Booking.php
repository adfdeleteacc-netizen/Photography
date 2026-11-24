<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};

class Booking extends Model
{
    public const INQUIRY = 'inquiry';
    public const PENDING_PAYMENT = 'pending_payment';
    public const CONFIRMED = 'confirmed';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';
    public const REFUNDED = 'refunded';

    /** Allowed state machine transitions. */
    public const TRANSITIONS = [
        self::INQUIRY => [self::PENDING_PAYMENT, self::CANCELLED],
        self::PENDING_PAYMENT => [self::CONFIRMED, self::CANCELLED],
        self::CONFIRMED => [self::COMPLETED, self::CANCELLED, self::REFUNDED],
        self::COMPLETED => [self::REFUNDED],
        self::CANCELLED => [self::REFUNDED],
        self::REFUNDED => [],
    ];

    protected $guarded = [];
    protected $casts = [
        'starts_at' => 'datetime', 'ends_at' => 'datetime',
        'confirmed_at' => 'datetime', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime',
    ];

    public function canTransitionTo(string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function customer(): BelongsTo { return $this->belongsTo(User::class, 'customer_id'); }
    public function photographer(): BelongsTo { return $this->belongsTo(PhotographerProfile::class, 'photographer_profile_id'); }
    public function studio(): BelongsTo { return $this->belongsTo(Studio::class); }
    public function package(): BelongsTo { return $this->belongsTo(ServicePackage::class, 'service_package_id'); }
    public function coupon(): BelongsTo { return $this->belongsTo(Coupon::class); }
    public function payment(): HasOne { return $this->hasOne(Payment::class)->latestOfMany(); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
    public function statusLogs(): HasMany { return $this->hasMany(BookingStatusLog::class); }
    public function review(): HasOne { return $this->hasOne(Review::class); }
    public function conversation(): HasOne { return $this->hasOne(Conversation::class); }
    public function settlementItem(): HasOne { return $this->hasOne(SettlementItem::class); }
}
