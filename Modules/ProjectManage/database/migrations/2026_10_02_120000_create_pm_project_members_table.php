<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('pm_projects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
        });

        // Tasks can only be assigned to project members, so anyone already assigned a
        // task becomes a member of that task's project.
        $now = now();
        $rows = DB::table('pm_tasks')
            ->whereNotNull('assigned_to')
            ->select('project_id', 'assigned_to')
            ->distinct()
            ->get()
            ->map(fn ($r) => [
                'project_id' => $r->project_id,
                'user_id'    => $r->assigned_to,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if ($rows) {
            DB::table('pm_project_members')->insertOrIgnore($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_project_members');
    }
};
