<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_product_brand', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_brand_id')->constrained('product_brands')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'product_brand_id']);
            $table->index('product_brand_id');
        });

        if (Schema::hasColumn('products', 'product_brand_id')) {
            $rows = DB::table('products')
                ->whereNotNull('product_brand_id')
                ->select('id', 'product_brand_id')
                ->get();

            $now = now();
            foreach ($rows as $row) {
                DB::table('product_product_brand')->insertOrIgnore([
                    'product_id' => $row->id,
                    'product_brand_id' => $row->product_brand_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            Schema::table('products', function (Blueprint $table): void {
                $table->dropForeign(['product_brand_id']);
                $table->dropIndex(['business_id', 'product_brand_id']);
                $table->dropColumn('product_brand_id');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('products', 'product_brand_id')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->foreignId('product_brand_id')->nullable()->after('business_id')->constrained('product_brands')->nullOnDelete();
                $table->index(['business_id', 'product_brand_id']);
            });

            $pivotRows = DB::table('product_product_brand')
                ->select('product_id', DB::raw('MIN(product_brand_id) as product_brand_id'))
                ->groupBy('product_id')
                ->get();

            foreach ($pivotRows as $row) {
                DB::table('products')
                    ->where('id', $row->product_id)
                    ->update(['product_brand_id' => $row->product_brand_id]);
            }
        }

        Schema::dropIfExists('product_product_brand');
    }
};
