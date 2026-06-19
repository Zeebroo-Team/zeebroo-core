<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_tables', function (Blueprint $table): void {
            $table->smallInteger('pos_x')->nullable()->after('notes');
            $table->smallInteger('pos_y')->nullable()->after('pos_x');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_tables', function (Blueprint $table): void {
            $table->dropColumn(['pos_x', 'pos_y']);
        });
    }
};
