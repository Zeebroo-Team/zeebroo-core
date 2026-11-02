<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Line-level discount (an amount) and tax (a rate in %, with the amount it came to).
        // line_total stays qty × price − discount, before tax; old lines keep 0 / 0 / 0.
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->decimal('discount_amount', 14, 2)->default(0)->after('unit_price');
            $table->decimal('tax_rate', 6, 3)->default(0)->after('discount_amount');
            $table->decimal('tax_amount', 14, 2)->default(0)->after('tax_rate');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropColumn(['discount_amount', 'tax_rate', 'tax_amount']);
        });
    }
};
