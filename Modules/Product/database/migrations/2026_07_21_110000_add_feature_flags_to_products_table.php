<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('has_warranty')->default(false)->after('is_bundle');
            $table->boolean('track_expiry')->default(false)->after('has_warranty');
            $table->boolean('courier_delivery')->default(false)->after('track_expiry');
            $table->boolean('loyalty_redeemable')->default(false)->after('courier_delivery');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['has_warranty', 'track_expiry', 'courier_delivery', 'loyalty_redeemable']);
        });
    }
};
