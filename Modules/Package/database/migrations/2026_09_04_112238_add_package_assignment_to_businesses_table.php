<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->foreignId('package_id')->nullable()->after('user_id')
                ->constrained('packages')->nullOnDelete();
            $table->boolean('has_unlimited_access')->default(false)->after('package_id');
        });

        Schema::create('business_feature_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('feature_key');
            $table->boolean('enabled');
            $table->timestamps();

            $table->unique(['business_id', 'feature_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_feature_overrides');

        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('package_id');
            $table->dropColumn('has_unlimited_access');
        });
    }
};
