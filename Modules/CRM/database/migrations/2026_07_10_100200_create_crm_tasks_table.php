<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->nullableMorphs('subject');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_tasks');
    }
};
