<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_item_service_category', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_item_id')->constrained('service_items')->cascadeOnDelete();
            $table->foreignId('service_category_id')->constrained('service_categories')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['service_item_id', 'service_category_id']);
            $table->index('service_category_id');
        });

        // Migrate existing string category values into the pivot table.
        if (Schema::hasColumn('service_items', 'category')) {
            $now = now();
            $items = DB::table('service_items')->whereNotNull('category')->select('id', 'business_id', 'category')->get();

            foreach ($items as $item) {
                $cat = DB::table('service_categories')
                    ->where('business_id', $item->business_id)
                    ->where('name', $item->category)
                    ->first();

                if (!$cat) {
                    $catId = DB::table('service_categories')->insertGetId([
                        'business_id' => $item->business_id,
                        'name'        => $item->category,
                        'is_active'   => true,
                        'sort_order'  => 0,
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ]);
                } else {
                    $catId = $cat->id;
                }

                DB::table('service_item_service_category')->insertOrIgnore([
                    'service_item_id'     => $item->id,
                    'service_category_id' => $catId,
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('service_item_service_category');
    }
};
