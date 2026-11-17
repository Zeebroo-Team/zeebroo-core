<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per automation (welcome, password reset, …). Rows are created lazily
        // from the defaults in AutomatedEmailService, so a missing row means "default".
        Schema::create('admin_automated_emails', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->boolean('is_enabled')->default(true);
            $table->string('subject', 200);
            $table->longText('body');
            $table->json('settings')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('admin_automated_email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50);
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->string('subject', 255)->nullable();
            $table->string('status', 20); // sent | failed
            $table->text('error')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['key', 'created_at']);
            $table->index(['key', 'user_id', 'created_at']);
        });

        // Last time the user used the web app or the desktop/mobile API — drives the inactivity reminder.
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable()->after('remember_token')->index();
        });

        // Backfill from the most recent login so existing users aren't all treated as inactive.
        DB::table('users')->update([
            'last_seen_at' => DB::raw('COALESCE((SELECT MAX(l.created_at) FROM user_activity_logs l WHERE l.user_id = users.id), users.created_at)'),
        ]);

        Schema::table('app_releases', function (Blueprint $table) {
            $table->timestamp('users_notified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('app_releases', function (Blueprint $table) {
            $table->dropColumn('users_notified_at');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['last_seen_at']);
            $table->dropColumn('last_seen_at');
        });
        Schema::dropIfExists('admin_automated_email_logs');
        Schema::dropIfExists('admin_automated_emails');
    }
};
