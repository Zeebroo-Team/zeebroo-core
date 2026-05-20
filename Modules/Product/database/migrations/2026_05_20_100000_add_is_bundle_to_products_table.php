<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('is_bundle')->default(false)->after('is_active');
            $table->index(['business_id', 'is_bundle']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['business_id', 'is_bundle']);
            $table->dropColumn('is_bundle');
        });
    }
};
