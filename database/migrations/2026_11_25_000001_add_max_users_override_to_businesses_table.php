<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            // Admin-set per-business user limit, replacing the package's max_users;
            // null = use the package's limit. Bought extra users are added on top.
            $table->unsignedInteger('max_users_override')->nullable()->after('payment_grace_days');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropColumn('max_users_override');
        });
    }
};
