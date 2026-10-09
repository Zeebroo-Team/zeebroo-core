<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Built-in statuses (todo / in_progress / review / done) can now be renamed, recoloured,
 * re-sorted and deleted per project. They are stored as override rows (key = built-in key);
 * a deleted built-in keeps its row with is_hidden = true.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_task_statuses', function (Blueprint $table) {
            $table->boolean('is_hidden')->default(false)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('pm_task_statuses', function (Blueprint $table) {
            $table->dropColumn('is_hidden');
        });
    }
};
