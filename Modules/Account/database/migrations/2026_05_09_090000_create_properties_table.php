<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('property_name');
            $table->string('property_type');
            $table->decimal('cost', 15, 2)->default(0);
            $table->boolean('has_expiry')->default(false);
            $table->date('expire_date')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'created_at']);
            $table->index(['business_id', 'has_expiry', 'expire_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
