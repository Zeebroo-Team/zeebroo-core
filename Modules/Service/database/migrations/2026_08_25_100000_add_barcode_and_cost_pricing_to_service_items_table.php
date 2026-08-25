<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_items', function (Blueprint $table) {
            $table->string('barcode', 120)->nullable()->after('name');
            $table->decimal('cost_price', 12, 2)->nullable()->after('price');
            $table->decimal('wholesale_price', 12, 2)->nullable()->after('cost_price');
        });
    }

    public function down(): void
    {
        Schema::table('service_items', function (Blueprint $table) {
            $table->dropColumn(['barcode', 'cost_price', 'wholesale_price']);
        });
    }
};
