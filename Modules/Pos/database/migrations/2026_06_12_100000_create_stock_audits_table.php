<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_audits', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('audit_number', 40);
            $table->date('audit_date');
            $table->string('status', 20)->default('open');
            $table->text('notes')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['business_id', 'audit_number']);
            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'audit_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_audits');
    }
};
