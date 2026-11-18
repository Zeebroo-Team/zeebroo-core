<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Prepared by" on the printed quotation: who made it, and their name / designation /
        // contact number as they were then (a later profile change does not rewrite old quotations)
        Schema::table('quotations', function (Blueprint $table) {
            $table->foreignId('prepared_by')->nullable()->after('total')->constrained('users')->nullOnDelete();
            $table->string('prepared_by_name', 120)->nullable()->after('prepared_by');
            $table->string('prepared_by_designation', 120)->nullable()->after('prepared_by_name');
            $table->string('prepared_by_phone', 40)->nullable()->after('prepared_by_designation');
        });

        // Lead quotations already submitted: whoever submitted them prepared them
        DB::table('quotations')
            ->whereNull('prepared_by')
            ->whereNotNull('submitted_by')
            ->select(['id', 'submitted_by'])
            ->chunkById(500, function ($rows) {
                $names = DB::table('users')->whereIn('id', $rows->pluck('submitted_by')->unique())->pluck('name', 'id');
                foreach ($rows as $row) {
                    DB::table('quotations')->where('id', $row->id)->update([
                        'prepared_by'      => $row->submitted_by,
                        'prepared_by_name' => $names[$row->submitted_by] ?? null,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prepared_by');
            $table->dropColumn(['prepared_by_name', 'prepared_by_designation', 'prepared_by_phone']);
        });
    }
};
