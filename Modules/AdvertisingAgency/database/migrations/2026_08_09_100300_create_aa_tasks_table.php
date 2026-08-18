<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aa_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('aa_campaigns')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority', 20)->default('medium');
            $table->string('status', 30)->default('todo');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aa_tasks');
    }
};
