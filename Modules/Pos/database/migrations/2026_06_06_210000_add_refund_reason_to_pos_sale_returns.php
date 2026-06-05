<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sale_returns', function (Blueprint $table): void {
            $table->string('refund_reason', 100)->nullable()->after('refund_method');
        });
    }

    public function down(): void
    {
        Schema::table('pos_sale_returns', function (Blueprint $table): void {
            $table->dropColumn('refund_reason');
        });
    }
};
