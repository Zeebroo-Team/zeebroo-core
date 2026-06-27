<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_menu_item_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_item_id')->constrained('restaurant_menu_items')->cascadeOnDelete();
            $table->foreignId('menu_category_id')->constrained('restaurant_menu_categories')->cascadeOnDelete();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->unique(['menu_item_id', 'menu_category_id']);
        });

        // Migrate existing single-category assignments into the pivot
        DB::table('restaurant_menu_items')
            ->whereNotNull('menu_category_id')
            ->select('id', 'menu_category_id')
            ->orderBy('id')
            ->each(function ($row): void {
                DB::table('restaurant_menu_item_categories')->insertOrIgnore([
                    'menu_item_id'     => $row->id,
                    'menu_category_id' => $row->menu_category_id,
                    'sort_order'       => 0,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_menu_item_categories');
    }
};
