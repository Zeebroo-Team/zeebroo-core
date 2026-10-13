<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mobile app offline mode (customers and expenses): the phone gives each
 * customer or expense it records offline a unique id (client_uuid), so a
 * retried upload is recognised instead of saved twice. Stays null for every
 * record created any other way.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pos_customers') && ! Schema::hasColumn('pos_customers', 'client_uuid')) {
            Schema::table('pos_customers', function (Blueprint $table): void {
                $table->uuid('client_uuid')->nullable()->after('business_id');
                $table->unique(['business_id', 'client_uuid'], 'pos_customers_business_client_uuid_unique');
            });
        }

        if (Schema::hasTable('bills') && ! Schema::hasColumn('bills', 'client_uuid')) {
            Schema::table('bills', function (Blueprint $table): void {
                $table->uuid('client_uuid')->nullable()->after('business_id');
                $table->unique(['business_id', 'client_uuid'], 'bills_business_client_uuid_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pos_customers', 'client_uuid')) {
            Schema::table('pos_customers', function (Blueprint $table): void {
                $table->dropUnique('pos_customers_business_client_uuid_unique');
                $table->dropColumn('client_uuid');
            });
        }

        if (Schema::hasColumn('bills', 'client_uuid')) {
            Schema::table('bills', function (Blueprint $table): void {
                $table->dropUnique('bills_business_client_uuid_unique');
                $table->dropColumn('client_uuid');
            });
        }
    }
};
