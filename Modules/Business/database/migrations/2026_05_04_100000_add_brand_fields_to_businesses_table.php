<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->string('company_category_slug', 64)->nullable()->after('category');
            $table->string('short_description', 380)->nullable()->after('description');
            $table->json('brand_features')->nullable()->after('short_description');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropColumn(['company_category_slug', 'short_description', 'brand_features']);
        });
    }
};
