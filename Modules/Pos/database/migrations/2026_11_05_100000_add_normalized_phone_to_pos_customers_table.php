<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_customers', function (Blueprint $table): void {
            // Digits with the country code ("94771234567"), kept by the CRM for the duplicate check;
            // fill existing rows with `php artisan crm:normalize-customer-phones --apply`
            $table->string('normalized_phone', 32)->nullable()->after('phone');
            $table->index(['business_id', 'normalized_phone'], 'pos_customers_business_normalized_phone_index');
            $table->index(['business_id', 'email'], 'pos_customers_business_email_index');
        });
    }

    public function down(): void
    {
        Schema::table('pos_customers', function (Blueprint $table): void {
            $table->dropIndex('pos_customers_business_normalized_phone_index');
            $table->dropIndex('pos_customers_business_email_index');
            $table->dropColumn('normalized_phone');
        });
    }
};
