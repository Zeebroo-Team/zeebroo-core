<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_menu_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('file_manager_file_id')->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_menu_items', function (Blueprint $table): void {
            $table->dropColumn('file_manager_file_id');
        });
    }
};
