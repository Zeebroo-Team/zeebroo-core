<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // More custom-stage ticks: items to finish before a lead leaves, and no jumping over the stage before
        Schema::table('crm_lead_stages', function (Blueprint $table) {
            $table->json('exit_checklist')->nullable()->after('locks_lead');
            $table->boolean('no_skip')->default(false)->after('exit_checklist');
        });

        // The checklist items done for the stage the lead is in now (cleared on every move)
        Schema::table('crm_leads', function (Blueprint $table) {
            $table->json('checklist_done')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            $table->dropColumn('checklist_done');
        });
        Schema::table('crm_lead_stages', function (Blueprint $table) {
            $table->dropColumn(['exit_checklist', 'no_skip']);
        });
    }
};
