<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_bundles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['business_id', 'is_active']);
        });

        Schema::create('service_bundle_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_bundle_id')->constrained('service_bundles')->cascadeOnDelete();
            $table->foreignId('service_item_id')->constrained('service_items')->cascadeOnDelete();
            $table->unsignedSmallInteger('qty')->default(1);
            $table->timestamps();

            $table->unique(['service_bundle_id', 'service_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_bundle_items');
        Schema::dropIfExists('service_bundles');
    }
};
