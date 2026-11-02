<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each time a quotation PDF goes to the customer, over one channel (email now, WhatsApp later)
        Schema::create('quotation_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->string('channel', 20);
            $table->string('to', 191);
            $table->json('cc')->nullable();
            $table->string('subject', 255)->nullable();
            $table->text('message')->nullable();
            $table->string('file_path', 255)->nullable();
            $table->string('status', 20)->default('queued');
            $table->text('error')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['quotation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_dispatches');
    }
};
