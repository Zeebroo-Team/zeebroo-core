<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_items', function (Blueprint $table) {
            $table->boolean('custom_requirement_enabled')->default(false)->after('has_warranty');
            $table->json('custom_requirement_fields')->nullable()->after('custom_requirement_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('service_items', function (Blueprint $table) {
            $table->dropColumn(['custom_requirement_enabled', 'custom_requirement_fields']);
        });
    }
};
