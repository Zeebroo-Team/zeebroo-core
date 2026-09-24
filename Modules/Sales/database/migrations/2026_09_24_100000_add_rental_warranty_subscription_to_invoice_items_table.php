<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('rental_daily_rate', 12, 2)->nullable()->after('line_total');
            $table->date('rental_return_date')->nullable()->after('rental_daily_rate');
            $table->decimal('rental_late_fee_multiplier', 8, 2)->nullable()->after('rental_return_date');
            $table->string('warranty_type', 10)->nullable()->after('rental_late_fee_multiplier');
            $table->date('warranty_date')->nullable()->after('warranty_type');
            $table->boolean('is_subscription')->default(false)->after('warranty_date');
            $table->string('subscription_period', 20)->nullable()->after('is_subscription');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn([
                'rental_daily_rate',
                'rental_return_date',
                'rental_late_fee_multiplier',
                'warranty_type',
                'warranty_date',
                'is_subscription',
                'subscription_period',
            ]);
        });
    }
};
