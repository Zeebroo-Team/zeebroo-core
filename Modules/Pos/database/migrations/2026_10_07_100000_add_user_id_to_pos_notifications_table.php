<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Null = business-wide (everyone sees it); set = only that user sees it
        // (e.g. "you were added to a project" / "a task was assigned to you").
        Schema::table('pos_notifications', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->after('branch_id');
            $table->index(['business_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('pos_notifications', function (Blueprint $table): void {
            $table->dropIndex(['business_id', 'user_id']);
            $table->dropColumn('user_id');
        });
    }
};
