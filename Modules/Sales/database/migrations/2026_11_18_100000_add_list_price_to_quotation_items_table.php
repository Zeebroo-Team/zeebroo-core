<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The product's standard (catalog) price when the line was saved — unit_price differing
        // from it is a price change the approver sees. Null for custom / service lines.
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->decimal('list_price', 15, 2)->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropColumn('list_price');
        });
    }
};
