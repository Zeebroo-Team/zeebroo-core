<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Leads keyed in through the desktop New Lead modal used to be stamped 'pipeline-form';
 * they are staff-entered, so they now count as the 'manual' lead source.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('crm_leads')->where('source', 'pipeline-form')->update(['source' => 'manual']);
    }

    public function down(): void
    {
        // Irreversible: 'manual' leads can't be told apart from ones that were already manual.
    }
};
