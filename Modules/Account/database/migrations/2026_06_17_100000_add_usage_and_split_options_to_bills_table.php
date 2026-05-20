<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table): void {
            $table->boolean('amount_varies_by_usage')->default(false)->after('recurring_type');
            $table->boolean('allow_split_payment')->default(true)->after('amount_varies_by_usage');
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table): void {
            $table->dropColumn(['amount_varies_by_usage', 'allow_split_payment']);
        });
    }
};
