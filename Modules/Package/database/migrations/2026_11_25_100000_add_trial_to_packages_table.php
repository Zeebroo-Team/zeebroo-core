<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table): void {
            // A free-trial package: no payment gateway, expires after trial_days,
            // shown first/highlighted in onboarding, and only once per user.
            $table->boolean('is_trial')->default(false)->after('is_free');
            $table->unsignedSmallInteger('trial_days')->nullable()->after('is_trial');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table): void {
            $table->dropColumn(['is_trial', 'trial_days']);
        });
    }
};
