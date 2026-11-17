<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What happens when a lead enters the stage (LeadStage::entryRules); null = nothing extra
        Schema::table('crm_lead_stages', function (Blueprint $table) {
            $table->json('entry_rules')->nullable()->after('requires_reason');
        });
    }

    public function down(): void
    {
        Schema::table('crm_lead_stages', function (Blueprint $table) {
            $table->dropColumn('entry_rules');
        });
    }
};
