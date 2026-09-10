<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_flows', function (Blueprint $table) {
            $table->json('trigger_config')->nullable()->after('trigger_type'); // e.g. {"relation_id": 5}
        });
    }

    public function down(): void
    {
        Schema::table('automation_flows', function (Blueprint $table) {
            $table->dropColumn('trigger_config');
        });
    }
};
