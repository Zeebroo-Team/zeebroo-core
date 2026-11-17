<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The products a lead is interested in (free-text extras live in crm_leads.product_interest_note)
        Schema::create('crm_lead_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['lead_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_lead_products');
    }
};
