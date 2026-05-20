<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['business_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
