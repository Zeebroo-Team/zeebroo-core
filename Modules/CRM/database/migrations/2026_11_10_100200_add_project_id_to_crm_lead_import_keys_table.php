<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A row is remembered per pipeline, so the same row can be imported into another one
        Schema::table('crm_lead_import_keys', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('business_id')->constrained('crm_projects')->cascadeOnDelete();
        });

        DB::table('crm_lead_import_keys')->whereNotNull('import_id')->update([
            'project_id' => DB::raw('(SELECT project_id FROM crm_lead_imports WHERE crm_lead_imports.id = crm_lead_import_keys.import_id)'),
        ]);

        // The new index goes first: MySQL needs one on business_id for its foreign key
        Schema::table('crm_lead_import_keys', function (Blueprint $table) {
            $table->unique(['business_id', 'project_id', 'row_key']);
            $table->dropUnique(['business_id', 'row_key']);
        });
    }

    public function down(): void
    {
        // Only one key per row can stay once rows are remembered per business again
        $keep = DB::table('crm_lead_import_keys')->selectRaw('MIN(id) as id')->groupBy('business_id', 'row_key')->pluck('id');
        DB::table('crm_lead_import_keys')->whereNotIn('id', $keep)->delete();

        Schema::table('crm_lead_import_keys', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->unique(['business_id', 'row_key']);
            $table->dropUnique(['business_id', 'project_id', 'row_key']);
            $table->dropColumn('project_id');
        });
    }
};
