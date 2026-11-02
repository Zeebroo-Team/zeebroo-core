<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_notifications', function (Blueprint $table): void {
            // Where the notification leads (e.g. crm/leads/12); the desktop deep-links from the payload
            $table->string('action_url', 255)->nullable()->after('payload');

            // Per-user notifications about the same record (two marketing users, one new lead)
            // collided on the old key, which had no user_id
            $table->dropUnique('pos_notifications_dedupe_unique');
            $table->unique(['business_id', 'user_id', 'type', 'reference_type', 'reference_id'], 'pos_notifications_dedupe_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pos_notifications', function (Blueprint $table): void {
            $table->dropUnique('pos_notifications_dedupe_unique');
            $table->unique(['business_id', 'type', 'reference_type', 'reference_id'], 'pos_notifications_dedupe_unique');
            $table->dropColumn('action_url');
        });
    }
};
