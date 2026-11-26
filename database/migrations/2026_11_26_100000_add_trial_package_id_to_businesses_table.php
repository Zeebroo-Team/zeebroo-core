<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            // The package whose features a business tries out while on the free
            // trial (its package_id stays the trial package). Cleared when the
            // business moves to a real plan.
            $table->foreignId('trial_package_id')->nullable()->after('trial_ends_at')
                ->constrained('packages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('trial_package_id');
        });
    }
};
