<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->string('google_location_resource', 512)->nullable()->after('brand_features');
            $table->string('google_location_title_cache', 255)->nullable()->after('google_location_resource');
            $table->timestamp('google_location_linked_at')->nullable()->after('google_location_title_cache');

            $table->index('google_location_resource');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropIndex(['google_location_resource']);
            $table->dropColumn([
                'google_location_resource',
                'google_location_title_cache',
                'google_location_linked_at',
            ]);
        });
    }
};
