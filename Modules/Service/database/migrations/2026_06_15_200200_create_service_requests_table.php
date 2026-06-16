<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('service_item_id')->nullable()->constrained('service_items')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('pos_customers')->nullOnDelete();
            $table->string('request_number', 40)->nullable();
            $table->string('title', 255);
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->string('status', 20)->default('pending');
            $table->decimal('total_price', 14, 2)->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'created_at']);
            $table->unique(['business_id', 'request_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
