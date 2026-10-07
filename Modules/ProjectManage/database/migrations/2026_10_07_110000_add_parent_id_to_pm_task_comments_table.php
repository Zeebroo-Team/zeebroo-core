<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_task_comments', function (Blueprint $table) {
            // Replies point at their top-level comment. No FK constraint: on SQLite that would
            // rebuild the table; replies are removed together with the task (task_id cascade).
            $table->unsignedBigInteger('parent_id')->nullable()->after('user_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('pm_task_comments', function (Blueprint $table) {
            $table->dropIndex(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};
