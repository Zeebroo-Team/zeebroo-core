<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('business_id')
                ->constrained('branches')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
