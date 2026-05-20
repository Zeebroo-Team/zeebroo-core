<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('file_manager_file_id')->constrained('file_manager_files')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'file_manager_file_id']);
        });

        if (Schema::hasColumn('products', 'file_manager_file_id')) {
            foreach (DB::table('products')->whereNotNull('file_manager_file_id')->cursor() as $row) {
                DB::table('product_images')->insertOrIgnore([
                    'product_id' => $row->id,
                    'file_manager_file_id' => $row->file_manager_file_id,
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
