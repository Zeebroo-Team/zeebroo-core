<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_logo_generations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('status', 24)->default('pending')->index();
            $table->string('company_category', 64);
            $table->string('logo_style', 64);
            $table->string('background_theme', 16);
            $table->text('custom_prompt')->nullable();
            $table->string('logo_path')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_logo_generations');
    }
};
