<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A stage's own icon and a short note on what it means; null = use its standard status's
        Schema::table('crm_lead_stages', function (Blueprint $table) {
            $table->string('icon', 40)->nullable()->after('color');
            $table->string('description', 255)->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('crm_lead_stages', function (Blueprint $table) {
            $table->dropColumn(['icon', 'description']);
        });
    }
};
