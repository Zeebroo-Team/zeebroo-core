<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table): void {
            $table->string('recurring_type', 20)->default('per_month')->after('interest_rate');
            $table->date('first_installment_due_date')->nullable()->after('recurring_type');
            $table->date('loan_ending_date')->nullable()->after('first_installment_due_date');
            $table->foreignId('deduct_account_id')->nullable()->after('loan_ending_date')->constrained('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('deduct_account_id');
            $table->dropColumn(['recurring_type', 'first_installment_due_date', 'loan_ending_date']);
        });
    }
};
