<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The management dashboard (CrmReportService) filters on these by date range
        Schema::table('crm_leads', function (Blueprint $table) {
            $table->index(['business_id', 'created_at']);
            $table->index(['business_id', 'closed_at']);
            $table->index(['business_id', 'assigned_to']);
        });
    }

    public function down(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'created_at']);
            $table->dropIndex(['business_id', 'closed_at']);
            $table->dropIndex(['business_id', 'assigned_to']);
        });
    }
};
