<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_departments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['business_id', 'name']);
            $table->index(['business_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_departments');
    }
};
