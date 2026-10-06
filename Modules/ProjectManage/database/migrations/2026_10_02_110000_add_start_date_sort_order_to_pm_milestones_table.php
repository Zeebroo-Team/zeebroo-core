<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_milestones', function (Blueprint $table) {
            if (! Schema::hasColumn('pm_milestones', 'start_date')) {
                $table->date('start_date')->nullable()->after('description');
            }
            if (! Schema::hasColumn('pm_milestones', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('status');
                $table->index(['project_id', 'sort_order']);
            }
        });

        // Number existing milestones 1..n per project in their previous (due date) order.
        $rows = DB::table('pm_milestones')
            ->orderBy('project_id')
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get(['id', 'project_id']);

        $counters = [];
        foreach ($rows as $row) {
            $counters[$row->project_id] = ($counters[$row->project_id] ?? 0) + 1;
            DB::table('pm_milestones')->where('id', $row->id)->update(['sort_order' => $counters[$row->project_id]]);
        }
    }

    public function down(): void
    {
        Schema::table('pm_milestones', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'sort_order']);
            $table->dropColumn(['start_date', 'sort_order']);
        });
    }
};
