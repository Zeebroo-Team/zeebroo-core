<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_item_discounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('service_item_id')->constrained('service_items')->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('discount_type', 12)->default('percentage'); // flat | percentage
            $table->decimal('discount_value', 10, 2);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['service_item_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_item_discounts');
    }
};
