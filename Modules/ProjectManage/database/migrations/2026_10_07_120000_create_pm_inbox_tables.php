<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * My Projects → Inbox: Gmail-style message threads between project team members.
 * Each participant keeps their own read / starred / archived / trashed state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_inbox_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('pm_projects')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject', 200);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'last_message_at']);
        });

        Schema::create('pm_inbox_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('pm_inbox_threads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Newest message the user has seen (ids, not times — two messages can share a second).
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            $table->boolean('is_starred')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('trashed_at')->nullable();
            $table->timestamps();

            $table->unique(['thread_id', 'user_id']);
            $table->index(['user_id', 'trashed_at', 'archived_at']);
        });

        Schema::create('pm_inbox_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('pm_inbox_threads')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['thread_id', 'created_at']);
        });

        Schema::create('pm_inbox_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('pm_inbox_messages')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name', 255);
            $table->string('stored_path', 500);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamps();

            $table->index(['message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_inbox_attachments');
        Schema::dropIfExists('pm_inbox_messages');
        Schema::dropIfExists('pm_inbox_participants');
        Schema::dropIfExists('pm_inbox_threads');
    }
};
