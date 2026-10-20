<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->string('slug');
            $table->string('locale', 8)->default('ka');
            $table->string('title');
            $table->longText('body');
            $table->string('status', 16)->default('draft');
            $table->timestamps();
            $table->unique(['slug', 'locale']);
        });

        Schema::create('contents', function (Blueprint $table): void {
            $table->id();
            $table->string('slug');
            $table->string('locale', 8)->default('ka');
            $table->string('title');
            $table->longText('body');
            $table->string('type', 32)->default('block');
            $table->string('status', 16)->default('draft');
            $table->timestamps();
            $table->unique(['slug', 'locale']);
        });

        Schema::create('settings', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->json('value');
            $table->timestamps();
        });

        Schema::create('media_items', function (Blueprint $table): void {
            $table->id();
            $table->string('original_name');
            $table->string('mime', 127);
            $table->string('path');
            $table->unsignedBigInteger('bytes');
            $table->string('alt')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_items');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('contents');
        Schema::dropIfExists('pages');
    }
};
