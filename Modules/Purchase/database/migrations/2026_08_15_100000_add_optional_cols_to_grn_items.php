<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receive_note_items', function (Blueprint $table) {
            $table->unsignedSmallInteger('units_per_case')->nullable()->after('selling_unit_price');
            $table->string('uom', 40)->nullable()->after('units_per_case');
            $table->decimal('discount_percent', 6, 3)->nullable()->after('uom');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receive_note_items', function (Blueprint $table) {
            $table->dropColumn(['units_per_case', 'uom', 'discount_percent']);
        });
    }
};
