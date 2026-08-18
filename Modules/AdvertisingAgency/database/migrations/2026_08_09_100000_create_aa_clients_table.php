<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aa_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->string('company', 150)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('address', 300)->nullable();
            $table->string('industry', 100)->nullable();
            $table->string('website', 255)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aa_clients');
    }
};
