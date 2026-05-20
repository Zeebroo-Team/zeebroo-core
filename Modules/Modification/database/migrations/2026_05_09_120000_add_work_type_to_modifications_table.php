<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modifications', function (Blueprint $table): void {
            $table->string('property_work_type', 40)->nullable()->after('assignment_reference');
            $table->string('property_work_type_other')->nullable()->after('property_work_type');
        });
    }

    public function down(): void
    {
        Schema::table('modifications', function (Blueprint $table): void {
            $table->dropColumn(['property_work_type', 'property_work_type_other']);
        });
    }
};

