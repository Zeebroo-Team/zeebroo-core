<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_customers', function (Blueprint $table): void {
            // null = no limit; credit sales may not push outstanding dues past this amount
            $table->decimal('credit_limit', 12, 2)->nullable()->after('customer_type');
        });
    }

    public function down(): void
    {
        Schema::table('pos_customers', function (Blueprint $table): void {
            $table->dropColumn('credit_limit');
        });
    }
};
