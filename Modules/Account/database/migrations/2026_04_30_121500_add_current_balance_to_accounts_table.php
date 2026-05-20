<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->decimal('current_balance', 15, 2)->default(0)->after('branch');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->dropColumn('current_balance');
        });
    }
};
