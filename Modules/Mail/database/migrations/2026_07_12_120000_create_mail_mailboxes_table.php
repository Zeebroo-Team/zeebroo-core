<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_mailboxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained('businesses')->cascadeOnDelete();
            $table->string('email_address', 190);
            $table->string('imap_host', 190);
            $table->unsignedInteger('imap_port')->default(993);
            $table->string('imap_username', 190);
            $table->text('imap_password');
            $table->string('imap_encryption', 10)->default('ssl');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('last_uid')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_sync_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_mailboxes');
    }
};
