<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Team invites by email. The business_members row is only created on accept, so the
        // invitee may not have a users row yet (they set a password on the invitation page).
        Schema::create('business_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role', 60);
            $table->json('permissions')->nullable();
            $table->string('token_hash', 64)->unique();
            $table->string('status', 20)->default('pending'); // pending | accepted | cancelled
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'email', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_invitations');
    }
};
