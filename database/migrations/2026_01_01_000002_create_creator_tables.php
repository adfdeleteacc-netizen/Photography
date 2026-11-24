<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('studios', function (Blueprint $t) {
            $t->id();
            $t->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $t->string('name');
            $t->string('slug')->unique();
            $t->text('about')->nullable();
            $t->string('city')->index();
            $t->text('address')->nullable();
            $t->string('logo_path')->nullable();
            $t->string('brand_color', 7)->nullable();
            $t->boolean('is_verified')->default(false)->index();
            $t->boolean('is_featured')->default(false)->index();
            $t->timestamps();
        });

        Schema::create('photographer_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('studio_id')->nullable()->constrained()->nullOnDelete();
            $t->string('display_name');
            $t->string('slug')->unique();
            $t->text('bio')->nullable();
            $t->string('city')->index();
            $t->unsignedTinyInteger('experience_years')->default(0);
            $t->unsignedBigInteger('starting_price_paise')->default(0)->index();
            $t->decimal('rating_avg', 3, 2)->default(0)->index();
            $t->unsignedInteger('rating_count')->default(0);
            $t->boolean('is_verified')->default(false)->index();
            $t->boolean('is_featured')->default(false)->index();
            $t->timestamps();
        });

        Schema::create('category_photographer', function (Blueprint $t) {
            $t->foreignId('photographer_profile_id')->constrained()->cascadeOnDelete();
            $t->foreignId('category_id')->constrained()->cascadeOnDelete();
            $t->primary(['photographer_profile_id', 'category_id']);
        });

        Schema::create('studio_members', function (Blueprint $t) {
            $t->id();
            $t->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('role')->default('photographer'); // manager | photographer | assistant
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['studio_id', 'user_id']);
        });

        // Services/packages a creator sells (what a customer actually books)
        Schema::create('service_packages', function (Blueprint $t) {
            $t->id();
            $t->morphs('owner'); // PhotographerProfile or Studio
            $t->foreignId('category_id')->constrained();
            $t->string('title');
            $t->text('description')->nullable();
            $t->unsignedBigInteger('price_paise');
            $t->unsignedSmallInteger('duration_minutes')->default(240);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('kyc_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('type'); // pan | gstin | identity
            $t->string('number')->nullable();
            $t->string('file_path')->nullable();
            $t->enum('status', ['pending', 'verified', 'rejected'])->default('pending')->index();
            $t->string('rejection_reason')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
        });

        Schema::create('bank_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('account_holder');
            $t->text('account_number'); // encrypted via model cast
            $t->string('ifsc', 11);
            $t->string('bank_name')->nullable();
            $t->string('razorpay_fund_account_id')->nullable();
            $t->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $t->boolean('is_primary')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['bank_accounts', 'kyc_documents', 'service_packages', 'studio_members',
                  'category_photographer', 'photographer_profiles', 'studios'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
