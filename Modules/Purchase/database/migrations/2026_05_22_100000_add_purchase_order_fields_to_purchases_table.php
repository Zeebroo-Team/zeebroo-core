<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->string('po_number', 40)->nullable()->after('business_id');
            $table->date('expected_delivery_date')->nullable()->after('purchase_date');
        });

        $this->backfillPoNumbers();

        Schema::table('purchases', function (Blueprint $table) {
            $table->unique(['business_id', 'po_number']);
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'po_number']);
            $table->dropColumn(['po_number', 'expected_delivery_date']);
        });
    }

    private function backfillPoNumbers(): void
    {
        $businessIds = DB::table('purchases')->distinct()->orderBy('business_id')->pluck('business_id');

        foreach ($businessIds as $businessId) {
            $rows = DB::table('purchases')
                ->where('business_id', $businessId)
                ->orderBy('id')
                ->get(['id', 'po_number']);

            $seq = 0;
            foreach ($rows as $row) {
                if (filled($row->po_number)) {
                    if (preg_match('/^PO-(\d+)$/', (string) $row->po_number, $matches)) {
                        $seq = max($seq, (int) $matches[1]);
                    }

                    continue;
                }

                $seq++;
                DB::table('purchases')->where('id', $row->id)->update([
                    'po_number' => 'PO-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
                ]);
            }
        }
    }
};
