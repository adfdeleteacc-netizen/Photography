<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $guarded = [];
    protected $casts = [
        'trial_ends_at' => 'datetime', 'starts_at' => 'datetime', 'ends_at' => 'datetime',
        'grace_ends_at' => 'datetime', 'cancelled_at' => 'datetime',
        'auto_renew' => 'boolean', 'reminders_sent' => 'array',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function plan(): BelongsTo { return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id'); }

    public function isUsable(): bool
    {
        return in_array($this->status, ['trialing', 'active', 'past_due'], true)
            && ($this->ends_at->isFuture() || ($this->grace_ends_at && $this->grace_ends_at->isFuture()));
    }
}
