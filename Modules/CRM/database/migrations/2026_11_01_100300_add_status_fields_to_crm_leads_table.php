<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // created_by already exists (2026_10_30_100000); the legacy free-text lost_reason
        // column stays as the optional note next to lost_reason_id.
        Schema::table('crm_leads', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('project_id')
                ->constrained('hr_departments')->nullOnDelete();
            $table->foreignId('division_id')->nullable()->after('department_id')
                ->constrained('crm_divisions')->nullOnDelete();
            $table->foreignId('source_id')->nullable()->after('source')
                ->constrained('crm_lead_sources')->nullOnDelete();
            $table->string('external_id', 191)->nullable()->after('source_id');
            $table->string('score', 10)->nullable()->after('estimated_value');
            $table->foreignId('lost_reason_id')->nullable()->after('lost_reason')
                ->constrained('crm_lost_reasons')->nullOnDelete();
            $table->text('product_interest_note')->nullable()->after('notes');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('on_hold_from_stage_id')->nullable()
                ->constrained('crm_lead_stages')->nullOnDelete();

            $table->index(['business_id', 'phone']);
            $table->index(['business_id', 'email']);
            $table->index(['business_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'phone']);
            $table->dropIndex(['business_id', 'email']);
            $table->dropIndex(['business_id', 'department_id']);

            $table->dropConstrainedForeignId('on_hold_from_stage_id');
            $table->dropConstrainedForeignId('verified_by');
            $table->dropConstrainedForeignId('lost_reason_id');
            $table->dropConstrainedForeignId('source_id');
            $table->dropConstrainedForeignId('division_id');
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn(['external_id', 'score', 'product_interest_note', 'verified_at', 'first_response_at', 'closed_at']);
        });
    }
};
