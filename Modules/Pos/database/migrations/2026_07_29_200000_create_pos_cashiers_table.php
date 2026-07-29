<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_cashiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('username', 80);
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['business_id', 'username']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_cashiers');
    }
};
