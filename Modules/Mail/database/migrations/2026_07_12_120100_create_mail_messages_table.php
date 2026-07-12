<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('mailbox_id')->nullable()->constrained('mail_mailboxes')->nullOnDelete();
            $table->string('direction', 10); // inbound | outbound
            $table->unsignedBigInteger('uid')->nullable(); // IMAP UID, inbound only
            $table->string('message_id', 255)->nullable(); // Message-ID header
            $table->string('from_address', 190)->nullable();
            $table->string('from_name', 190)->nullable();
            $table->text('to_address')->nullable();
            $table->string('subject', 500)->nullable();
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'direction', 'occurred_at']);
            $table->unique(['mailbox_id', 'uid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_messages');
    }
};
