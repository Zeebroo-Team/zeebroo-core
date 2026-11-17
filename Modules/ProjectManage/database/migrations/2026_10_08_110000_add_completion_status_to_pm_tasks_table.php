<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every task now has a completion status (incomplete / complete / cancelled) next to its
 * stage (the board column kept in `status`). Tasks already in the Done stage start as complete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_tasks', function (Blueprint $table) {
            $table->string('completion_status', 20)->default('incomplete')->after('status')->index();
        });

        DB::table('pm_tasks')->where('status', 'done')->update(['completion_status' => 'complete']);
    }

    public function down(): void
    {
        Schema::table('pm_tasks', function (Blueprint $table) {
            $table->dropIndex(['completion_status']);
            $table->dropColumn('completion_status');
        });
    }
};
