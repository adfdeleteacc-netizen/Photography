<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Recurring weekly working hours
        Schema::create('availability_slots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('photographer_profile_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('day_of_week'); // 0=Sun..6=Sat
            $t->time('start_time');
            $t->time('end_time');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['photographer_profile_id', 'day_of_week']);
        });

        Schema::create('blocked_dates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('photographer_profile_id')->constrained()->cascadeOnDelete();
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->string('reason')->nullable();
            $t->foreignId('booking_id')->nullable(); // set when a confirmed booking locks the slot
            $t->timestamps();
            $t->index(['photographer_profile_id', 'starts_at', 'ends_at']);
        });

        Schema::create('bookings', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 20)->unique();
            $t->foreignId('customer_id')->constrained('users');
            $t->foreignId('photographer_profile_id')->constrained();
            $t->foreignId('studio_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete(); // studio assignment
            $t->foreignId('service_package_id')->constrained();
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->string('location')->nullable();
            $t->text('notes')->nullable();
            $t->enum('status', ['inquiry', 'pending_payment', 'confirmed', 'completed', 'cancelled', 'refunded'])
                ->default('inquiry')->index();

            // Pricing snapshot (paise) — frozen at booking time
            $t->unsignedBigInteger('base_amount_paise');
            $t->unsignedBigInteger('coupon_discount_paise')->default(0);
            $t->unsignedBigInteger('loyalty_discount_paise')->default(0);
            $t->unsignedBigInteger('total_amount_paise');          // what customer pays
            $t->decimal('commission_percent', 5, 2);
            $t->unsignedBigInteger('commission_paise');
            $t->unsignedBigInteger('gst_on_commission_paise');
            $t->unsignedBigInteger('tcs_paise');
            $t->unsignedBigInteger('creator_net_paise');           // payable to creator
            $t->string('coupon_code', 40)->nullable();   // requested at inquiry
            $t->foreignId('coupon_id')->nullable();    // set once reserved at acceptance
            $t->unsignedInteger('loyalty_points_used')->default(0);

            $t->timestamp('payment_due_at')->nullable();  // slot hold expiry while pending_payment
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->timestamps();

            $t->index(['photographer_profile_id', 'starts_at', 'ends_at']);
        });

        Schema::create('booking_status_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $t->string('from_status')->nullable();
            $t->string('to_status');
            $t->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('note')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['booking_status_logs', 'bookings', 'blocked_dates', 'availability_slots'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
