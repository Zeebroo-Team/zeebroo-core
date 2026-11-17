<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A "Custom" stage picks its behaviour by ticks instead of a standard status
        Schema::table('crm_lead_stages', function (Blueprint $table) {
            $table->boolean('is_custom')->default(false)->after('custom_status_id');
            $table->boolean('needs_approval')->default(false)->after('requires_reason');
            $table->boolean('locks_lead')->default(false)->after('needs_approval');
        });

        // A move into a stage that needs approval waits here until the head decides
        Schema::create('crm_stage_move_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('lead_id')->index();
            $table->unsignedBigInteger('from_stage_id')->nullable();
            $table->unsignedBigInteger('to_stage_id');
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->json('context')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_stage_move_requests');
        Schema::table('crm_lead_stages', function (Blueprint $table) {
            $table->dropColumn(['is_custom', 'needs_approval', 'locks_lead']);
        });
    }
};
