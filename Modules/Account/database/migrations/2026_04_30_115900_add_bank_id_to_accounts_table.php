<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->foreignId('bank_id')
                ->nullable()
                ->after('bank_type_id')
                ->constrained('banks')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('bank_id');
        });
    }
};
