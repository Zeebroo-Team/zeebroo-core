<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receive_notes', function (Blueprint $table) {
            // Make purchase_id optional so GRNs can be created without a PO
            $table->dropForeign(['purchase_id']);
            $table->unsignedBigInteger('purchase_id')->nullable()->change();
            $table->foreign('purchase_id')->references('id')->on('purchases')->cascadeOnDelete();

            // Direct supplier link for GRNs that have no purchase order
            $table->foreignId('supplier_id')
                ->nullable()
                ->after('purchase_id')
                ->constrained('suppliers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('goods_receive_notes', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn('supplier_id');

            $table->dropForeign(['purchase_id']);
            $table->unsignedBigInteger('purchase_id')->nullable(false)->change();
            $table->foreign('purchase_id')->references('id')->on('purchases')->cascadeOnDelete();
        });
    }
};
