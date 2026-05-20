<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table): void {
            $table->boolean('rental_property_related')->default(false)->after('business_id');
            $table->foreignId('rental_id')->nullable()->after('rental_property_related')->constrained('rentals')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('rental_id');
            $table->dropColumn('rental_property_related');
        });
    }
};
