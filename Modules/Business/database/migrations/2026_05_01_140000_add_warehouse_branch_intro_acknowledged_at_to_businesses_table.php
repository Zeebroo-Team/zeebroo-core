<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->timestamp('warehouse_branch_intro_acknowledged_at')->nullable();
        });

        DB::table('businesses')->update(['warehouse_branch_intro_acknowledged_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropColumn('warehouse_branch_intro_acknowledged_at');
        });
    }
};
