<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Purchase\Models\GoodsReceiveNote;
use Modules\Purchase\Models\Purchase;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cheque_payments')) {
            return;
        }

        $ledgerRows = DB::table('ledger_transactions')
            ->where('meta->payment_method', Purchase::PAYMENT_CHEQUE)
            ->orderBy('id')
            ->get();

        foreach ($ledgerRows as $ledger) {
            if (DB::table('cheque_payments')->where('ledger_transaction_id', $ledger->id)->exists()) {
                continue;
            }

            $meta = json_decode((string) $ledger->meta, true) ?: [];
            $grnId = null;
            if ($ledger->transactionable_type === GoodsReceiveNote::class) {
                $grnId = (int) $ledger->transactionable_id;
            }

            $dueDate = $meta['cheque_due_date'] ?? null;
            if (! filled($dueDate) && $grnId) {
                $dueDate = DB::table('goods_receive_notes')->where('id', $grnId)->value('cheque_due_date');
            }
            if (! filled($dueDate)) {
                $dueDate = $ledger->occurrence_date ?? $ledger->created_at;
            }

            $chequeNumber = trim((string) ($meta['payment_reference'] ?? ''));
            if ($chequeNumber === '' && $grnId) {
                $chequeNumber = (string) (DB::table('goods_receive_notes')->where('id', $grnId)->value('payment_reference') ?? '');
            }
            if ($chequeNumber === '') {
                $chequeNumber = '—';
            }

            DB::table('cheque_payments')->insert([
                'business_id' => $ledger->business_id,
                'user_id' => $ledger->user_id,
                'goods_receive_note_id' => $grnId ?: null,
                'ledger_transaction_id' => $ledger->id,
                'deduct_account_id' => $ledger->deduct_account_id,
                'cheque_number' => $chequeNumber,
                'due_date' => $dueDate,
                'amount' => $ledger->amount,
                'status' => 'cleared',
                'cleared_at' => $ledger->occurrence_date ?? $ledger->created_at,
                'created_at' => $ledger->created_at,
                'updated_at' => $ledger->updated_at,
            ]);
        }

        $grnRows = DB::table('goods_receive_notes')
            ->where(function ($query): void {
                $query->where('payment_method', Purchase::PAYMENT_CHEQUE)
                    ->orWhereNotNull('cheque_due_date');
            })
            ->orderBy('id')
            ->get();

        foreach ($grnRows as $grn) {
            $hasChequeRow = DB::table('cheque_payments')
                ->where('goods_receive_note_id', $grn->id)
                ->exists();

            if ($hasChequeRow) {
                continue;
            }

            $paid = (float) DB::table('ledger_transactions')
                ->where('transactionable_type', GoodsReceiveNote::class)
                ->where('transactionable_id', $grn->id)
                ->sum('amount');
            $outstanding = max(0, round((float) $grn->total - $paid, 2));

            if ($outstanding <= 0.005) {
                continue;
            }

            $dueDate = $grn->cheque_due_date;
            if (! filled($dueDate)) {
                continue;
            }

            $chequeNumber = trim((string) ($grn->payment_reference ?? ''));
            if ($chequeNumber === '') {
                continue;
            }

            DB::table('cheque_payments')->insert([
                'business_id' => $grn->business_id,
                'user_id' => null,
                'goods_receive_note_id' => $grn->id,
                'ledger_transaction_id' => null,
                'deduct_account_id' => null,
                'cheque_number' => $chequeNumber,
                'due_date' => $dueDate,
                'amount' => $outstanding,
                'status' => 'pending',
                'cleared_at' => null,
                'created_at' => $grn->created_at,
                'updated_at' => $grn->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        // Backfill only; leave rows on rollback of this migration if table remains.
    }
};
