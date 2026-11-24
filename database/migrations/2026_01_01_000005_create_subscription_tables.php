<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $t) {
            $t->id();
            $t->string('name');                       // Starter | Pro | Studio Enterprise
            $t->string('slug')->unique();
            $t->enum('interval', ['monthly', 'annual']);
            $t->unsignedBigInteger('price_paise');
            $t->unsignedSmallInteger('trial_days')->default(0);
            // Feature limits (null = unlimited)
            $t->unsignedInteger('max_portfolio_uploads')->nullable();
            $t->unsignedInteger('max_bookings_per_month')->nullable();
            $t->unsignedSmallInteger('max_team_members')->nullable();
            $t->boolean('featured_listing')->default(false);
            $t->boolean('analytics_access')->default(false);
            $t->boolean('for_studios')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('subscription_plan_id')->constrained();
            $t->enum('status', ['trialing', 'active', 'past_due', 'cancelled', 'expired'])->index();
            $t->timestamp('trial_ends_at')->nullable();
            $t->timestamp('starts_at');
            $t->timestamp('ends_at')->index();
            $t->timestamp('grace_ends_at')->nullable();
            $t->boolean('auto_renew')->default(true);
            $t->foreignId('pending_plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete(); // scheduled downgrade
            $t->timestamp('cancelled_at')->nullable();
            $t->json('reminders_sent')->nullable();
            $t->timestamps();
        });

        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->string('number')->unique();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('subscription_plan_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedBigInteger('amount_paise');
            $t->unsignedBigInteger('gst_paise')->default(0);
            $t->unsignedBigInteger('total_paise');
            $t->enum('status', ['pending', 'paid', 'failed', 'void'])->default('pending')->index();
            $t->string('razorpay_order_id')->nullable()->index();
            $t->string('razorpay_payment_id')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
