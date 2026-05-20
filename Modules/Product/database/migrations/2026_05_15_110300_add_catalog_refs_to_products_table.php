<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('product_category_id')->nullable()->after('business_id')->constrained('product_categories')->nullOnDelete();
            $table->foreignId('product_brand_id')->nullable()->after('product_category_id')->constrained('product_brands')->nullOnDelete();
            $table->foreignId('product_unit_id')->nullable()->after('product_brand_id')->constrained('product_units')->nullOnDelete();

            $table->index(['business_id', 'product_category_id']);
            $table->index(['business_id', 'product_brand_id']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropForeign(['product_category_id']);
            $table->dropForeign(['product_brand_id']);
            $table->dropForeign(['product_unit_id']);
            $table->dropColumn(['product_category_id', 'product_brand_id', 'product_unit_id']);
        });
    }
};
