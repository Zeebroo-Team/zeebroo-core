<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('file_manager_file_id')
                  ->nullable()
                  ->constrained('file_manager_files')
                  ->nullOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('mode', 12)->default('storewide'); // storewide | individual
            // storewide: the discount applied to every product.
            // individual: an optional default used only to pre-fill new campaign
            // items client-side — each item's own discount is what actually applies.
            $table->string('discount_type', 12)->nullable(); // flat | percentage
            $table->decimal('discount_value', 10, 2)->nullable();
            $table->boolean('is_long_term')->default(false); // true = ongoing, ignore ends_at
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['business_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_campaigns');
    }
};
