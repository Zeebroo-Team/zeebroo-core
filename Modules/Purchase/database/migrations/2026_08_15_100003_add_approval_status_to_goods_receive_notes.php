<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receive_notes', function (Blueprint $table) {
            // null  = no approval required (stock applied immediately)
            // pending  = awaiting approval
            // approved = stock applied after approval
            // rejected = rejected by approver
            $table->string('approval_status', 20)->nullable()->after('stock_applied');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receive_notes', function (Blueprint $table) {
            $table->dropColumn('approval_status');
        });
    }
};
