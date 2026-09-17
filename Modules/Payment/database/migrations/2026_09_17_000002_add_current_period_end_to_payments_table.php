<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            // End of the current Stripe billing period for an active subscription —
            // used to show/warn about the next renewal date before it's charged.
            $table->timestamp('current_period_end')->nullable()->after('stripe_subscription_status');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('current_period_end');
        });
    }
};
