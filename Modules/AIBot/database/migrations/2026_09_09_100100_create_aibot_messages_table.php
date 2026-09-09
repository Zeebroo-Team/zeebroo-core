<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aibot_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('aibot_conversations')->cascadeOnDelete();
            $table->string('role', 20);
            $table->text('content')->nullable();
            $table->boolean('is_voice')->default(false);
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aibot_messages');
    }
};
