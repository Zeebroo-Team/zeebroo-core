<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('subscription_recurring_period', 30)->nullable()->after('is_subscription');
            $table->boolean('subscription_free_trial')->default(false)->after('subscription_recurring_period');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'subscription_recurring_period', 'subscription_free_trial',
            ]);
        });
    }
};
