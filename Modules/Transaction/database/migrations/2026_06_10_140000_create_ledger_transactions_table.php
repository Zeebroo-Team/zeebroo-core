<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Account\Models\Loan;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('transactionable');
            $table->foreignId('deduct_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->date('occurrence_date');
            $table->unsignedSmallInteger('period_number')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 8)->nullable();
            $table->string('cadence_snapshot', 32)->nullable();
            $table->unsignedSmallInteger('periods_total_snapshot')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(
                ['transactionable_type', 'transactionable_id', 'occurrence_date'],
                'ledger_tx_subject_occurrence_unique'
            );
            $table->index(['business_id', 'occurrence_date']);
        });

        $loanMorph = Loan::class;

        if (Schema::hasTable('loan_deduction_transactions')) {
            DB::table('loan_deduction_transactions')
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($loanMorph): void {
                    foreach ($rows as $old) {
                        $meta = null;
                        if ($old->borrowed_principal_snapshot !== null) {
                            $meta = json_encode([
                                'borrowed_principal_snapshot' => (float) $old->borrowed_principal_snapshot,
                            ]);
                        }

                        DB::table('ledger_transactions')->insert([
                            'business_id' => $old->business_id,
                            'user_id' => $old->user_id,
                            'transactionable_type' => $loanMorph,
                            'transactionable_id' => $old->loan_id,
                            'deduct_account_id' => $old->deduct_account_id,
                            'occurrence_date' => $old->deduction_date,
                            'period_number' => $old->period_number,
                            'amount' => $old->amount,
                            'currency' => $old->currency,
                            'cadence_snapshot' => $old->cadence_snapshot,
                            'periods_total_snapshot' => $old->periods_total_snapshot,
                            'meta' => $meta,
                            'created_at' => $old->created_at,
                            'updated_at' => $old->updated_at ?? $old->created_at,
                        ]);
                    }
                });

            Schema::drop('loan_deduction_transactions');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_transactions');

        Schema::create('loan_deduction_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deduct_account_id')->constrained('accounts')->restrictOnDelete();
            $table->date('deduction_date');
            $table->unsignedSmallInteger('period_number')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 8)->nullable();
            $table->string('cadence_snapshot', 32)->nullable();
            $table->unsignedSmallInteger('periods_total_snapshot')->nullable();
            $table->decimal('borrowed_principal_snapshot', 15, 2)->nullable();
            $table->timestamps();
            $table->unique(['loan_id', 'deduction_date']);
            $table->index(['business_id', 'created_at']);
        });
    }
};
