<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            // Deadline for settling a pending/failed subscription payment before
            // access is locked — set once when the payment first becomes due,
            // not extended by checkout retries.
            $table->timestamp('due_at')->nullable()->after('failure_reason');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('due_at');
        });
    }
};
