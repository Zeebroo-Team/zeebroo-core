<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table): void {
            // null = unlimited users
            $table->unsignedInteger('max_users')->nullable()->after('currency');
            // Charge per user beyond max_users (in the package currency). null = extra users not sold.
            $table->decimal('extra_user_price', 10, 2)->nullable()->after('max_users');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table): void {
            $table->dropColumn(['max_users', 'extra_user_price']);
        });
    }
};
