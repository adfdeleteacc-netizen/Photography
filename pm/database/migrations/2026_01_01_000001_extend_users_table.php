<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('phone', 20)->nullable()->unique()->after('email');
            $t->string('referral_code', 12)->nullable()->unique();
            $t->foreignId('referred_by')->nullable()->constrained('users')->nullOnDelete();
            $t->boolean('is_active')->default(true);
            $t->softDeletes();
        });

        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('referred_by');
            $t->dropColumn(['phone', 'referral_code', 'is_active', 'deleted_at']);
        });
    }
};
