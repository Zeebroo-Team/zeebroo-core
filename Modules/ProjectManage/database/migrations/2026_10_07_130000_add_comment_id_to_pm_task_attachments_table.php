<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_task_attachments', function (Blueprint $table) {
            // Files attached to a comment (null = a task-level attachment). No FK constraint: on
            // SQLite that would rebuild the table; rows are removed with the task (task_id cascade).
            $table->unsignedBigInteger('comment_id')->nullable()->after('user_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('pm_task_attachments', function (Blueprint $table) {
            $table->dropIndex(['comment_id']);
            $table->dropColumn('comment_id');
        });
    }
};
