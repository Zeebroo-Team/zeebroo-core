<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sale_returns', function (Blueprint $table): void {
            $table->dropForeign(['pos_sale_id']);
            $table->unsignedBigInteger('pos_sale_id')->nullable()->change();
            $table->foreign('pos_sale_id')->references('id')->on('pos_sales')->nullOnDelete();
        });

        Schema::table('pos_sale_return_items', function (Blueprint $table): void {
            $table->dropForeign(['pos_sale_item_id']);
            $table->unsignedBigInteger('pos_sale_item_id')->nullable()->change();
            $table->foreign('pos_sale_item_id')->references('id')->on('pos_sale_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pos_sale_returns', function (Blueprint $table): void {
            $table->dropForeign(['pos_sale_id']);
            $table->unsignedBigInteger('pos_sale_id')->nullable(false)->change();
            $table->foreign('pos_sale_id')->references('id')->on('pos_sales')->cascadeOnDelete();
        });

        Schema::table('pos_sale_return_items', function (Blueprint $table): void {
            $table->dropForeign(['pos_sale_item_id']);
            $table->unsignedBigInteger('pos_sale_item_id')->nullable(false)->change();
            $table->foreign('pos_sale_item_id')->references('id')->on('pos_sale_items')->cascadeOnDelete();
        });
    }
};
