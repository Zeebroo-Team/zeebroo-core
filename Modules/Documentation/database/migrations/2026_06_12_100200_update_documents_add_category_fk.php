<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('document_category_id')
                ->nullable()
                ->after('created_by')
                ->constrained('document_categories')
                ->nullOnDelete();
        });

        // Migrate existing string category values to the new FK
        $map = DB::table('document_categories')->pluck('id', 'slug');
        foreach ($map as $slug => $id) {
            DB::table('documents')->where('category', $slug)->update(['document_category_id' => $id]);
        }

        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'category']);
            $table->dropColumn('category');
            $table->index(['business_id', 'document_category_id']);
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('category', 50)->default('general')->after('document_category_id');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['document_category_id']);
            $table->dropIndex(['business_id', 'document_category_id']);
            $table->dropColumn('document_category_id');
            $table->index(['business_id', 'category']);
        });
    }
};
