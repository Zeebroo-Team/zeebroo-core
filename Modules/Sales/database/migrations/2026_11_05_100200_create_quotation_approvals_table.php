<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every step of a lead quotation's approval: submitted, approved, returned or rejected
        Schema::create('quotation_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->string('action', 20);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['quotation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_approvals');
    }
};
