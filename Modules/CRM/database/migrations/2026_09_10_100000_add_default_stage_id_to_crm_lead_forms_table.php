<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_lead_forms', function (Blueprint $table) {
            $table->foreignId('default_stage_id')->nullable()->after('success_message')
                ->constrained('crm_lead_stages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('crm_lead_forms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_stage_id');
        });
    }
};
