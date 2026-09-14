<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bm_brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('short_code', 3);
            $table->string('email', 150);
            $table->string('phone', 40)->nullable();
            $table->string('company_name', 150)->nullable();
            $table->string('contact_person', 150)->nullable();
            $table->string('address', 500)->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'short_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bm_brands');
    }
};
