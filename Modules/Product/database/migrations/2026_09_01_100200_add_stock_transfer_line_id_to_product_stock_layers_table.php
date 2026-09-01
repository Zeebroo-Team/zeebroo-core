<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_stock_layers', function (Blueprint $table): void {
            $table->foreignId('stock_transfer_line_id')->nullable()->after('goods_receive_note_item_id')
                ->constrained('stock_transfer_lines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_stock_layers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('stock_transfer_line_id');
        });
    }
};
