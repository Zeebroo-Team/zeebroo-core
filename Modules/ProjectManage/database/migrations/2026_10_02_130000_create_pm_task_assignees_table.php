<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_task_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('pm_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'user_id']);
            $table->index('user_id');
        });

        // Carry over the single assignee of existing tasks.
        $now = now();
        $rows = DB::table('pm_tasks')
            ->whereNotNull('assigned_to')
            ->get(['id', 'assigned_to'])
            ->map(fn ($t) => [
                'task_id'    => $t->id,
                'user_id'    => $t->assigned_to,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if ($rows) {
            DB::table('pm_task_assignees')->insertOrIgnore($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_task_assignees');
    }
};
