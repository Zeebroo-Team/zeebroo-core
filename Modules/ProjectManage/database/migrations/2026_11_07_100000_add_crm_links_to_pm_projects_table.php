<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The won CRM lead / accepted quotation a project was started from
        Schema::table('pm_projects', function (Blueprint $table) {
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pm_projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lead_id');
            $table->dropConstrainedForeignId('quotation_id');
        });
    }
};
