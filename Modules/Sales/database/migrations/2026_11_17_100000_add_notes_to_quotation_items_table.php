<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An optional note per line (colour, size, delivery remark…), printed under the item.
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
