<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DECISION: follow-ups get their own table instead of a type on crm_tasks. They hang off
        // a quotation, carry a channel / outcome and chain to the next one, and are cancelled and
        // rescheduled by the quotation workflow — none of which the generic task screens know.
        Schema::create('crm_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->unsignedBigInteger('quotation_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->timestamp('due_at');
            $table->timestamp('done_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('channel', 20)->nullable();
            $table->string('outcome', 30)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            // Set by crm:process-follow-ups so each reminder goes out once
            $table->timestamp('due_notified_at')->nullable();
            $table->timestamp('overdue_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['business_id', 'status', 'due_at']);
            $table->index(['lead_id', 'status']);
            $table->index(['user_id', 'status', 'due_at']);
            $table->index('quotation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_follow_ups');
    }
};
