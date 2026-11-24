<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->enum('type', ['percentage', 'fixed']);
            $t->unsignedBigInteger('value');                // percent (e.g. 10) or paise
            $t->unsignedBigInteger('max_discount_paise')->nullable();
            $t->unsignedBigInteger('min_order_paise')->default(0);
            $t->unsignedInteger('usage_limit')->nullable();
            $t->unsignedInteger('per_user_limit')->default(1);
            $t->unsignedInteger('used_count')->default(0);
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('coupon_usages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('discount_paise');
            $t->timestamps();
        });

        Schema::create('referrals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('referred_id')->unique()->constrained('users')->cascadeOnDelete();
            $t->enum('status', ['pending', 'rewarded'])->default('pending');
            $t->unsignedInteger('reward_points')->default(0);
            $t->timestamp('rewarded_at')->nullable();
            $t->timestamps();
        });

        Schema::create('loyalty_wallets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('balance')->default(0);
            $t->timestamps();
        });

        Schema::create('loyalty_transactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('loyalty_wallet_id')->constrained()->cascadeOnDelete();
            $t->enum('type', ['earn', 'redeem', 'referral', 'promotion', 'reversal']);
            $t->bigInteger('points');                      // signed
            $t->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $t->string('note')->nullable();
            $t->timestamps();
        });

        Schema::create('promotions', function (Blueprint $t) {
            $t->id();
            $t->morphs('promotable'); // PhotographerProfile | Studio
            $t->enum('kind', ['featured', 'ad']);
            $t->unsignedBigInteger('price_paise')->default(0);
            $t->string('city')->nullable()->index();
            $t->timestamp('starts_at');
            $t->timestamp('ends_at')->index();
            $t->enum('status', ['pending', 'active', 'expired', 'cancelled'])->default('pending')->index();
            $t->timestamps();
        });

        Schema::create('campaigns', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('channel')->default('email'); // email | push
            $t->text('body')->nullable();
            $t->json('audience')->nullable();
            $t->timestamp('scheduled_at')->nullable();
            $t->enum('status', ['draft', 'scheduled', 'sent'])->default('draft');
            $t->timestamps();
        });

        Schema::create('reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete(); // one review / booking
            $t->foreignId('customer_id')->constrained('users');
            $t->foreignId('photographer_profile_id')->constrained();
            $t->unsignedTinyInteger('rating');
            $t->text('body')->nullable();
            $t->json('media')->nullable();
            $t->boolean('is_visible')->default(true);
            $t->timestamps();
            $t->index(['photographer_profile_id', 'is_visible']);
        });

        Schema::create('conversations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained('users');
            $t->foreignId('creator_user_id')->constrained('users');
            $t->timestamp('last_message_at')->nullable();
            $t->timestamps();
        });

        Schema::create('messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('sender_id')->constrained('users');
            $t->text('body');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->index(['conversation_id', 'id']);
        });

        Schema::create('fraud_flags', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $t->string('reason');
            $t->enum('status', ['open', 'cleared', 'confirmed'])->default('open');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['fraud_flags', 'messages', 'conversations', 'reviews', 'campaigns', 'promotions',
                  'loyalty_transactions', 'loyalty_wallets', 'referrals', 'coupon_usages', 'coupons'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
