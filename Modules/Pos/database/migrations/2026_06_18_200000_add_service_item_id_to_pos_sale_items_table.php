<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sale_items', function (Blueprint $table) {
            $table->unsignedBigInteger('service_item_id')->nullable()->after('product_id');
            $table->foreign('service_item_id')->references('id')->on('service_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pos_sale_items', function (Blueprint $table) {
            $table->dropForeign(['service_item_id']);
            $table->dropColumn('service_item_id');
        });
    }
};
