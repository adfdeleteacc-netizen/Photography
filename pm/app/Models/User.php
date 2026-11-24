<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;

    protected $fillable = ['name', 'email', 'phone', 'password', 'referral_code', 'referred_by', 'is_active'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'is_active' => 'boolean'];
    }

    public function photographerProfile(): HasOne { return $this->hasOne(PhotographerProfile::class); }
    public function studio(): HasOne { return $this->hasOne(Studio::class, 'owner_id'); }
    public function subscriptions(): HasMany { return $this->hasMany(Subscription::class); }
    public function kycDocuments(): HasMany { return $this->hasMany(KycDocument::class); }
    public function bankAccounts(): HasMany { return $this->hasMany(BankAccount::class); }
    public function bookingsAsCustomer(): HasMany { return $this->hasMany(Booking::class, 'customer_id'); }
    public function loyaltyWallet(): HasOne { return $this->hasOne(LoyaltyWallet::class); }

    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->whereIn('status', ['trialing', 'active', 'past_due'])
            ->where(function ($q) {
                $q->where('ends_at', '>=', now())->orWhere('grace_ends_at', '>=', now());
            })->latest('ends_at')->first();
    }

    public function isKycVerified(): bool
    {
        return $this->kycDocuments()->where('type', 'pan')->where('status', 'verified')->exists();
    }
}
