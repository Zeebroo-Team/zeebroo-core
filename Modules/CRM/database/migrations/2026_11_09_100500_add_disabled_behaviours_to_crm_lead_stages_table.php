<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Built-in behaviours of the stage's standard status switched off (LeadStage::BUILT_IN_BEHAVIOURS); null = all on
        Schema::table('crm_lead_stages', function (Blueprint $table) {
            $table->json('disabled_behaviours')->nullable()->after('no_skip');
        });
    }

    public function down(): void
    {
        Schema::table('crm_lead_stages', function (Blueprint $table) {
            $table->dropColumn('disabled_behaviours');
        });
    }
};
