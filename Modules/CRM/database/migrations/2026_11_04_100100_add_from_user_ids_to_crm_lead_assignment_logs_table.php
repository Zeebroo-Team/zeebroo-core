<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The assignees before an (re/un)assignment; user_ids holds them after. Both lists put
        // the primary assignee first, so the history can say who was added, removed or made primary.
        Schema::table('crm_lead_assignment_logs', function (Blueprint $table) {
            $table->json('from_user_ids')->nullable()->after('user_ids');
        });
    }

    public function down(): void
    {
        Schema::table('crm_lead_assignment_logs', function (Blueprint $table) {
            $table->dropColumn('from_user_ids');
        });
    }
};
