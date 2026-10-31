<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Leads created before LeadService::create copied the pipeline's department kept a null
 * department_id, so approvals showed "No department" and fell back to "Any admin" instead of
 * the department head. Give every lead in a department-owned pipeline that department.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('crm_leads')
            ->join('crm_projects', 'crm_projects.id', '=', 'crm_leads.project_id')
            ->whereNotNull('crm_projects.department_id')
            ->where(fn ($q) => $q->whereNull('crm_leads.department_id')
                ->orWhereColumn('crm_leads.department_id', '!=', 'crm_projects.department_id'))
            ->select('crm_leads.id', 'crm_projects.department_id')
            ->get()
            ->groupBy('department_id')
            ->each(fn ($leads, $departmentId) => DB::table('crm_leads')
                ->whereIn('id', $leads->pluck('id'))
                ->update(['department_id' => $departmentId, 'division_id' => null]));
    }

    public function down(): void
    {
        // Irreversible: the null department_id was never intended.
    }
};
