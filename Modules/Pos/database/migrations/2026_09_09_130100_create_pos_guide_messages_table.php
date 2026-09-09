<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_guide_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('role', 20);
            $table->text('content')->nullable();
            $table->boolean('is_voice')->default(false);
            $table->timestamps();

            $table->foreign('conversation_id')->references('id')->on('pos_guide_conversations')->cascadeOnDelete();
            $table->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_guide_messages');
    }
};
