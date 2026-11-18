<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_customers', function (Blueprint $table): void {
            // The customer's line of business (e.g. "Construction"), captured on CRM quotations
            $table->string('industry', 120)->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('pos_customers', function (Blueprint $table): void {
            $table->dropColumn('industry');
        });
    }
};
