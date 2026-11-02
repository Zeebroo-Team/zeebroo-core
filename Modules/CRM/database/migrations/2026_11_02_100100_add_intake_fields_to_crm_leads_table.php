<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            // Digits with the country code ("94771234567"); filled by LeadService, backfilled by crm:normalize-lead-phones
            $table->string('normalized_phone', 32)->nullable()->after('phone');
            // Flagged for Marketing: a repeat enquiry, a match with an existing customer or a closed lead
            $table->boolean('needs_review')->default(false);
            $table->string('review_reason', 30)->nullable();
            $table->foreignId('duplicate_of_lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();
            // The department whose pipeline / form the lead was entered for before intake took it
            $table->foreignId('requested_department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $table->timestamp('forwarded_at')->nullable();
            $table->foreignId('forwarded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['business_id', 'normalized_phone']);
            $table->index(['business_id', 'needs_review']);
        });
    }

    public function down(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'normalized_phone']);
            $table->dropIndex(['business_id', 'needs_review']);

            $table->dropConstrainedForeignId('forwarded_by');
            $table->dropConstrainedForeignId('requested_department_id');
            $table->dropConstrainedForeignId('duplicate_of_lead_id');
            $table->dropColumn(['normalized_phone', 'needs_review', 'review_reason', 'forwarded_at']);
        });
    }
};
