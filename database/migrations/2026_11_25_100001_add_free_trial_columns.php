<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            // Set while the business runs on a trial package; cleared once it moves to a paid/free plan.
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('trial_reminder_sent_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table): void {
            // A user can start the free trial only once (across all their businesses).
            $table->timestamp('trial_used_at')->nullable();
        });

        Schema::table('business_members', function (Blueprint $table): void {
            // Set when a member is switched off because the business moved to a
            // package with fewer user seats (status is also set to 'pending',
            // which every access check already treats as "no access").
            $table->timestamp('deactivated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropColumn(['trial_ends_at', 'trial_reminder_sent_at']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('trial_used_at');
        });

        Schema::table('business_members', function (Blueprint $table): void {
            $table->dropColumn('deactivated_at');
        });
    }
};
