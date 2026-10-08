<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stage automation (the ⚡ on a board column): when a task enters the stage its completion
 * status is set to auto_completion_status (null = off). Done keeps its old behaviour — complete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_task_statuses', function (Blueprint $table) {
            $table->string('auto_completion_status', 20)->nullable()->after('is_hidden');
        });

        DB::table('pm_task_statuses')->where('key', 'done')->update(['auto_completion_status' => 'complete']);
    }

    public function down(): void
    {
        Schema::table('pm_task_statuses', function (Blueprint $table) {
            $table->dropColumn('auto_completion_status');
        });
    }
};
