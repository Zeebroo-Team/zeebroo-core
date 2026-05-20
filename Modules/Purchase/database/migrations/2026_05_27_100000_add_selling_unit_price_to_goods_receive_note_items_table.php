<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receive_note_items', function (Blueprint $table): void {
            $table->decimal('selling_unit_price', 14, 2)->nullable()->after('unit_cost');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receive_note_items', function (Blueprint $table): void {
            $table->dropColumn('selling_unit_price');
        });
    }
};
