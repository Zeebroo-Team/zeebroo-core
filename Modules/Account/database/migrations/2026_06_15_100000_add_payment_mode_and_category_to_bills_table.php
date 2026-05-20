<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table): void {
            $table->string('payment_mode', 16)->default('recurring')->after('name');
            $table->string('bill_category', 32)->default('other')->after('payment_mode');
            $table->string('bill_category_other', 255)->nullable()->after('bill_category');
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table): void {
            $table->dropColumn(['payment_mode', 'bill_category', 'bill_category_other']);
        });
    }
};
