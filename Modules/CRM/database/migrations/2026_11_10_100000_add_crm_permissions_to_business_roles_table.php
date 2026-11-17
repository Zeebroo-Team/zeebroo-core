<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Role-level CRM permission templates. Members of a role follow its template until an admin
 * customises them in crm_user_permissions; same JSON shapes as that table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_roles', function (Blueprint $table) {
            // JSON: { "leads_create": false, … } — a missing key means allowed
            $table->json('crm_permissions')->nullable()->after('permissions');
            // JSON: { "<project_id>": [<stage_id>, …] } — a missing relation means every stage is visible
            $table->json('crm_stage_access')->nullable()->after('crm_permissions');
        });
    }

    public function down(): void
    {
        Schema::table('business_roles', function (Blueprint $table) {
            $table->dropColumn(['crm_permissions', 'crm_stage_access']);
        });
    }
};
