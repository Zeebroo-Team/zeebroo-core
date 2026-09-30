<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_releases', function (Blueprint $table): void {
            // Which desktop client the release belongs to: main (electron_app) | lite (electron_app_pos_lite)
            $table->string('app', 16)->default('main')->after('id');
        });

        // Versions are now unique per app, so Lite and the main app can both ship e.g. v1.0.0
        Schema::table('app_releases', function (Blueprint $table): void {
            $table->dropUnique(['version']);
            $table->unique(['app', 'version']);
        });
    }

    public function down(): void
    {
        Schema::table('app_releases', function (Blueprint $table): void {
            $table->dropUnique(['app', 'version']);
            $table->unique('version');
        });

        Schema::table('app_releases', function (Blueprint $table): void {
            $table->dropColumn('app');
        });
    }
};
