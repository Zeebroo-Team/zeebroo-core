<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            // True once the customer asked to cancel: Stripe keeps the subscription
            // active until current_period_end, then stops renewing it.
            $table->boolean('cancel_at_period_end')->default(false)->after('current_period_end');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('cancel_at_period_end');
        });
    }
};
