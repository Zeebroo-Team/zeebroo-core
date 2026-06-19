<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('category', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['business_id', 'is_active']);
            $table->index(['business_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_items');
    }
};
