<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->foreignId('service_item_id')
                ->nullable()
                ->after('product_id')
                ->constrained('service_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropForeignIdFor(\Modules\Service\Models\ServiceItem::class);
            $table->dropColumn('service_item_id');
        });
    }
};
