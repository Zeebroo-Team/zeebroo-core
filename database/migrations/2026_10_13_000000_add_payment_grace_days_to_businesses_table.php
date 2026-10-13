<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            // Per-business override of config('subscription.grace_period_days'); null = use the default.
            $table->unsignedSmallInteger('payment_grace_days')->nullable()->after('package_id');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropColumn('payment_grace_days');
        });
    }
};
