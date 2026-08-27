<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('rental_daily_rate', 10, 2)->nullable()->after('is_rental');
            $table->unsignedInteger('rental_max_days')->nullable()->after('rental_daily_rate');
            $table->decimal('rental_late_fee_multiplier', 5, 2)->nullable()->after('rental_max_days');
            $table->boolean('rental_needs_cleaning')->default(false)->after('rental_late_fee_multiplier');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'rental_daily_rate', 'rental_max_days',
                'rental_late_fee_multiplier', 'rental_needs_cleaning',
            ]);
        });
    }
};
