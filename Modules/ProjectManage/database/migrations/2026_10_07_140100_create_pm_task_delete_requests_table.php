<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An assignee who does not own a task asks its owner to delete it.
        Schema::create('pm_task_delete_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('pm_tasks')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 500)->nullable();
            $table->string('status', 20)->default('pending');   // pending | rejected (approved rows go with the task)
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'status']);
            $table->index(['task_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_task_delete_requests');
    }
};
