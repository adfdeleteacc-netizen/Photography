<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('booking_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('razorpay_order_id')->unique();
            $t->string('razorpay_payment_id')->nullable()->unique();
            $t->unsignedBigInteger('amount_paise');
            $t->unsignedBigInteger('refunded_paise')->default(0);
            $t->enum('status', ['created', 'captured', 'failed', 'partially_refunded', 'refunded'])->default('created')->index();
            $t->json('gateway_payload')->nullable();
            $t->timestamps();
        });

        Schema::create('settlements', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 24)->unique();
            $t->foreignId('user_id')->constrained(); // creator being paid
            $t->enum('frequency', ['daily', 'weekly', 'manual']);
            $t->unsignedBigInteger('gross_paise');
            $t->unsignedBigInteger('commission_paise');
            $t->unsignedBigInteger('gst_paise');
            $t->unsignedBigInteger('tcs_paise');
            $t->unsignedBigInteger('net_paise');
            $t->enum('status', ['pending_approval', 'approved', 'processing', 'paid', 'failed'])->default('pending_approval')->index();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('payout_reference')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });

        Schema::create('settlement_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('settlement_id')->constrained()->cascadeOnDelete();
            $t->foreignId('booking_id')->unique()->constrained(); // a booking is settled once
            $t->unsignedBigInteger('net_paise');
            $t->timestamps();
        });

        Schema::create('transactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $t->enum('type', ['payment', 'refund', 'commission', 'gst', 'tcs', 'payout', 'subscription']);
            $t->unsignedBigInteger('amount_paise');
            $t->string('gateway_ref')->nullable();
            $t->timestamps();
        });

        // Double-entry style ledger: every row is a credit or debit to an account
        Schema::create('ledgers', function (Blueprint $t) {
            $t->id();
            $t->string('account');                 // platform_revenue | creator_payable:{userId} | gst_payable | tcs_payable | customer_escrow
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('settlement_id')->nullable()->constrained()->nullOnDelete();
            $t->enum('direction', ['credit', 'debit']);
            $t->unsignedBigInteger('amount_paise');
            $t->enum('entry_type', ['payment', 'commission', 'gst', 'tcs', 'refund', 'refund_reversal', 'settlement']);
            $t->string('description')->nullable();
            $t->timestamps();
            $t->index(['account', 'created_at']);
        });

        Schema::create('tax_invoices', function (Blueprint $t) {
            $t->id();
            $t->string('number')->unique();
            $t->foreignId('booking_id')->constrained();
            $t->foreignId('creator_user_id')->constrained('users');
            $t->string('creator_gstin', 15)->nullable();
            $t->string('place_of_supply', 2)->nullable(); // state code
            $t->unsignedBigInteger('taxable_value_paise'); // = commission
            $t->unsignedBigInteger('cgst_paise')->default(0);
            $t->unsignedBigInteger('sgst_paise')->default(0);
            $t->unsignedBigInteger('igst_paise')->default(0);
            $t->unsignedBigInteger('tcs_paise')->default(0);
            $t->boolean('is_credit_note')->default(false);
            $t->timestamps();
        });

        Schema::create('gst_reports', function (Blueprint $t) {
            $t->id();
            $t->string('type');                    // gstr8 | tax_summary | earnings
            $t->string('period', 7);               // YYYY-MM
            $t->string('file_path')->nullable();
            $t->json('summary')->nullable();
            $t->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['type', 'period']);
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->text('value')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['settings', 'gst_reports', 'tax_invoices', 'ledgers', 'transactions',
                  'settlement_items', 'settlements', 'payments'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
