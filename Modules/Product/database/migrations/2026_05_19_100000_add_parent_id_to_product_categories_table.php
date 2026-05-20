<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table): void {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('business_id')
                ->constrained('product_categories')
                ->cascadeOnDelete();
        });

        Schema::table('product_categories', function (Blueprint $table): void {
            $table->dropUnique(['business_id', 'name']);
        });

        Schema::table('product_categories', function (Blueprint $table): void {
            $table->unique(['business_id', 'parent_id', 'name']);
            $table->index(['business_id', 'parent_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table): void {
            $table->dropUnique(['business_id', 'parent_id', 'name']);
            $table->dropIndex(['business_id', 'parent_id', 'sort_order']);
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });

        Schema::table('product_categories', function (Blueprint $table): void {
            $table->unique(['business_id', 'name']);
        });
    }
};
