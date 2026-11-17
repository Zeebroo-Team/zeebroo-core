<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_tasks', function (Blueprint $table) {
            // The task's owner (who created it). Null on older tasks — the project creator owns those.
            // No FK constraint: on SQLite that would rebuild the table.
            $table->unsignedBigInteger('created_by')->nullable()->after('assigned_to')->index();
        });
    }

    public function down(): void
    {
        Schema::table('pm_tasks', function (Blueprint $table) {
            $table->dropIndex(['created_by']);
            $table->dropColumn('created_by');
        });
    }
};
