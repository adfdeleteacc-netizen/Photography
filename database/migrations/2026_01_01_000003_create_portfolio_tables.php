<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('portfolios', function (Blueprint $t) {
            $t->id();
            $t->morphs('owner');
            $t->foreignId('category_id')->constrained();
            $t->string('title');
            $t->string('slug')->unique();
            $t->text('description')->nullable();
            $t->string('type')->index(); // wedding|pre_wedding|product|fashion|corporate|event
            $t->boolean('is_featured')->default(false)->index();
            $t->boolean('is_published')->default(true)->index();
            $t->string('meta_title')->nullable();
            $t->string('meta_description')->nullable();
            $t->timestamps();
        });

        Schema::create('portfolio_media', function (Blueprint $t) {
            $t->id();
            $t->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $t->enum('kind', ['image', 'video']);
            $t->string('path');
            $t->string('watermarked_path')->nullable();
            $t->string('thumbnail_path')->nullable();
            $t->string('mime', 50);
            $t->unsignedBigInteger('size_bytes');
            $t->boolean('is_private')->default(false);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_media');
        Schema::dropIfExists('portfolios');
    }
};
