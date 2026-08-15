<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receive_note_items', function (Blueprint $table) {
            $table->dropForeign(['purchase_item_id']);
            $table->unsignedBigInteger('purchase_item_id')->nullable()->change();
            $table->foreign('purchase_item_id')->references('id')->on('purchase_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('goods_receive_note_items', function (Blueprint $table) {
            $table->dropForeign(['purchase_item_id']);
            $table->unsignedBigInteger('purchase_item_id')->nullable(false)->change();
            $table->foreign('purchase_item_id')->references('id')->on('purchase_items')->cascadeOnDelete();
        });
    }
};
