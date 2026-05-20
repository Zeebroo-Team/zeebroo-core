<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('file_manager_file_id')
                ->nullable()
                ->after('business_id')
                ->constrained('file_manager_files')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropForeign(['file_manager_file_id']);
            $table->dropColumn('file_manager_file_id');
        });
    }
};
