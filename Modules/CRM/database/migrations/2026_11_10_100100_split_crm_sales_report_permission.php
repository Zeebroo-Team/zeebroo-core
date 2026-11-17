<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The sales analysis report was covered by reports_view until it got its own reports_sales key.
 * A missing key means allowed, so copy each saved reports_view across: whoever was blocked from
 * the dashboard stays blocked from the report until an admin ticks it for them.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['crm_user_permissions' => 'permissions', 'business_roles' => 'crm_permissions'] as $table => $column) {
            DB::table($table)->whereNotNull($column)->orderBy('id')->get(['id', $column])->each(function ($row) use ($table, $column) {
                $permissions = json_decode($row->{$column}, true);
                if (! is_array($permissions) || ! array_key_exists('reports_view', $permissions) || array_key_exists('reports_sales', $permissions)) {
                    return;
                }
                $permissions['reports_sales'] = $permissions['reports_view'];
                DB::table($table)->where('id', $row->id)->update([$column => json_encode($permissions)]);
            });
        }
    }

    public function down(): void
    {
        // Nothing to undo: reports_sales is ignored once the key is gone from the catalogue.
    }
};
