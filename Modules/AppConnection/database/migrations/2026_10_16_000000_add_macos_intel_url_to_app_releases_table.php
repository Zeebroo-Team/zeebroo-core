<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_releases', function (Blueprint $table): void {
            // macos_url stays the Apple Silicon (arm64) build; this is the Intel (x64) build
            $table->string('macos_intel_url')->nullable()->after('macos_url');
        });
    }

    public function down(): void
    {
        Schema::table('app_releases', function (Blueprint $table): void {
            $table->dropColumn('macos_intel_url');
        });
    }
};
