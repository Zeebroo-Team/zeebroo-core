<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mobile app offline sales: the phone gives each sale it records offline a
 * unique id (client_uuid) so a retried upload is recognised instead of saved
 * twice, plus the temporary receipt number it printed (offline_ref, e.g.
 * OFF-7K2Q-0001). Both stay null for every other sale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sales', function (Blueprint $table): void {
            $table->uuid('client_uuid')->nullable()->after('sale_number');
            $table->string('offline_ref', 40)->nullable()->after('client_uuid');
            $table->unique(['business_id', 'client_uuid'], 'pos_sales_business_client_uuid_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pos_sales', function (Blueprint $table): void {
            $table->dropUnique('pos_sales_business_client_uuid_unique');
            $table->dropColumn(['client_uuid', 'offline_ref']);
        });
    }
};
