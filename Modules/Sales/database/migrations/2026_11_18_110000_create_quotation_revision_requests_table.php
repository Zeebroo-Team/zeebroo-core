<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A request to change an issued lead quotation: staff ask, the head approves (a new version
        // is made — revision_id) or rejects it; who edited the new version is kept too
        Schema::create('quotation_revision_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 20)->default('pending');
            $table->text('reason');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_comment')->nullable();
            $table->foreignId('revision_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->foreignId('revised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revised_at')->nullable();
            $table->timestamps();

            $table->index(['quotation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_revision_requests');
    }
};
