<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_categories', function (Blueprint $table): void {
            $table->string('icon', 64)->default('fa-briefcase')->after('name');
            $table->string('color', 9)->default('#4e8ef7')->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('business_categories', function (Blueprint $table): void {
            $table->dropColumn(['icon', 'color']);
        });
    }
};
