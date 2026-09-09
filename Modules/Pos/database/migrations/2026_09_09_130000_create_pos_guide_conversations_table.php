<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_guide_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->nullable();
            $table->string('actor_key', 80);
            $table->string('title', 160)->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->nullOnDelete();
            $table->index(['actor_key', 'business_id', 'last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_guide_conversations');
    }
};
