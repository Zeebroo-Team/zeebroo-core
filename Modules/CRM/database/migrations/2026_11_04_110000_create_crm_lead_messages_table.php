<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Internal discussion on a lead — never shown to the customer. One level of replies.
        Schema::create('crm_lead_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('crm_lead_messages')->cascadeOnDelete();
            $table->text('body');
            $table->json('mentions')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_lead_messages');
    }
};
