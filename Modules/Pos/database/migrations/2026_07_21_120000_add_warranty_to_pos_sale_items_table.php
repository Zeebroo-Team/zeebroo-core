<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sale_items', function (Blueprint $table) {
            $table->string('warranty_type', 20)->nullable()->after('sort_order'); // 'lifetime' | 'days'
            $table->unsignedSmallInteger('warranty_days')->nullable()->after('warranty_type');
            $table->date('warranty_expires_at')->nullable()->after('warranty_days');
        });
    }

    public function down(): void
    {
        Schema::table('pos_sale_items', function (Blueprint $table) {
            $table->dropColumn(['warranty_type', 'warranty_days', 'warranty_expires_at']);
        });
    }
};
