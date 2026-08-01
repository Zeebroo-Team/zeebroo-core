<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('model_no', 120)->nullable()->after('sku');
            $table->string('size', 120)->nullable()->after('model_no');
            $table->date('mfg_date')->nullable()->after('size');
            $table->date('exp_date')->nullable()->after('mfg_date');
            $table->boolean('is_customer_required')->default(false)->after('loyalty_redeemable');
            $table->boolean('is_rental')->default(false)->after('is_customer_required');
            $table->boolean('is_subscription')->default(false)->after('is_rental');
            $table->boolean('item_wise_tax')->default(false)->after('is_subscription');
            $table->boolean('item_wise_discount')->default(false)->after('item_wise_tax');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'model_no', 'size', 'mfg_date', 'exp_date',
                'is_customer_required', 'is_rental', 'is_subscription',
                'item_wise_tax', 'item_wise_discount',
            ]);
        });
    }
};
