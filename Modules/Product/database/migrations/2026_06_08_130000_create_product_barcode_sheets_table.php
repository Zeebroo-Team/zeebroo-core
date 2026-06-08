<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_barcode_sheets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('encode_type', 32)->default('CODE128'); // CODE128, CODE39, EAN13, EAN8, UPCA, QR
            $table->string('page_size', 16)->default('A4');        // A4, A5, Letter, Legal
            $table->string('page_orientation', 12)->default('portrait'); // portrait, landscape
            $table->string('label_type', 32)->default('with_name'); // barcode_only, with_name, with_name_price, with_sku
            $table->unsignedSmallInteger('labels_per_page')->default(12);
            $table->unsignedSmallInteger('total_quantity')->default(12);
            $table->timestamps();

            $table->index(['business_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_barcode_sheets');
    }
};
