<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_countries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('code', 3)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['business_id', 'name']);
            $table->index(['business_id', 'is_active']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('origin_country_id')->nullable()->after('size')
                ->constrained('product_countries')->nullOnDelete();
            $table->foreignId('manufacture_country_id')->nullable()->after('origin_country_id')
                ->constrained('product_countries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('manufacture_country_id');
            $table->dropConstrainedForeignId('origin_country_id');
        });

        Schema::dropIfExists('product_countries');
    }
};
