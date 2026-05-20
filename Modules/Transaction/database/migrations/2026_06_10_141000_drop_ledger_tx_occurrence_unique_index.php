<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bills (and optionally other subjects) allow multiple postings on the same
     * scheduled occurrence_date (partial follow-ups, split across accounts).
     */
    public function up(): void
    {
        Schema::table('ledger_transactions', function (Blueprint $table): void {
            $table->dropUnique('ledger_tx_subject_occurrence_unique');
        });
    }

    public function down(): void
    {
        Schema::table('ledger_transactions', function (Blueprint $table): void {
            $table->unique(
                ['transactionable_type', 'transactionable_id', 'occurrence_date'],
                'ledger_tx_subject_occurrence_unique'
            );
        });
    }
};
