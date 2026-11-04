<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A business's own statuses: reusable stage templates that count as a standard status in reports
        Schema::create('crm_custom_statuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->string('name', 60);
            $table->string('color', 20)->default('#6366f1');
            $table->string('icon', 40)->default('fa-star');
            $table->string('description', 255)->nullable();
            $table->string('base_key', 40);
            $table->boolean('requires_reason')->default(false);
            $table->json('entry_rules')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('crm_lead_stages', function (Blueprint $table) {
            $table->unsignedBigInteger('custom_status_id')->nullable()->after('system_key')->index();
        });
    }

    public function down(): void
    {
        Schema::table('crm_lead_stages', function (Blueprint $table) {
            $table->dropIndex(['custom_status_id']);
            $table->dropColumn('custom_status_id');
        });
        Schema::dropIfExists('crm_custom_statuses');
    }
};
