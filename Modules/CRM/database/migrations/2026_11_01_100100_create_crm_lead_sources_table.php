<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_lead_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('key', 40);
            $table->string('label', 100);
            $table->boolean('is_active')->default(true);
            // Connection settings for automatic sources (tokens, mappings) — encrypted by the model
            $table->text('config')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_lead_sources');
    }
};
