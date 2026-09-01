<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->foreignId('from_branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('to_branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('transfer_number', 40);
            $table->string('status', 20)->default('in_transit');
            $table->text('notes')->nullable();
            $table->foreignId('transferred_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('transferred_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'transfer_number']);
            $table->index(['business_id', 'status']);
            $table->index('from_branch_id');
            $table->index('to_branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};
