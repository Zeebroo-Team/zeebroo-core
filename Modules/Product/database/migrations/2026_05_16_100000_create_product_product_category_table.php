<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_product_category', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_category_id')->constrained('product_categories')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'product_category_id']);
            $table->index('product_category_id');
        });

        if (Schema::hasColumn('products', 'product_category_id')) {
            $rows = DB::table('products')
                ->whereNotNull('product_category_id')
                ->select('id', 'product_category_id')
                ->get();

            $now = now();
            foreach ($rows as $row) {
                DB::table('product_product_category')->insertOrIgnore([
                    'product_id' => $row->id,
                    'product_category_id' => $row->product_category_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            Schema::table('products', function (Blueprint $table): void {
                $table->dropForeign(['product_category_id']);
                $table->dropIndex(['business_id', 'product_category_id']);
                $table->dropColumn('product_category_id');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('products', 'product_category_id')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->foreignId('product_category_id')->nullable()->after('business_id')->constrained('product_categories')->nullOnDelete();
                $table->index(['business_id', 'product_category_id']);
            });

            $pivotRows = DB::table('product_product_category')
                ->select('product_id', DB::raw('MIN(product_category_id) as product_category_id'))
                ->groupBy('product_id')
                ->get();

            foreach ($pivotRows as $row) {
                DB::table('products')
                    ->where('id', $row->product_id)
                    ->update(['product_category_id' => $row->product_category_id]);
            }
        }

        Schema::dropIfExists('product_product_category');
    }
};
