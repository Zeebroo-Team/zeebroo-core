<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_audit_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_audit_id')->constrained('stock_audits')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_name', 255);
            $table->string('sku', 100)->nullable();
            $table->string('unit', 60)->nullable();
            $table->decimal('expected_qty', 14, 3)->default(0);
            $table->decimal('counted_qty', 14, 3)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['stock_audit_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_audit_lines');
    }
};
