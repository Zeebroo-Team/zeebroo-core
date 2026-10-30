<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A quotation built from a CRM lead: its versions, who submitted / approved it, the
        // approved PDF and when / how it went to the customer. All nullable — Sales, POS and
        // Design Studio quotations leave them empty.
        Schema::table('quotations', function (Blueprint $table) {
            $table->foreignId('lead_id')->nullable()->after('customer_id')
                ->constrained('crm_leads')->nullOnDelete();
            $table->unsignedInteger('version')->default(1)->after('quote_number');
            $table->foreignId('parent_quotation_id')->nullable()->after('version')
                ->constrained('quotations')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_comment')->nullable();
            $table->string('pdf_path', 255)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('sent_via', 20)->nullable();

            $table->index(['business_id', 'lead_id']);
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'lead_id']);

            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropConstrainedForeignId('parent_quotation_id');
            $table->dropConstrainedForeignId('lead_id');
            $table->dropColumn(['version', 'approved_at', 'approval_comment', 'pdf_path', 'sent_at', 'sent_via']);
        });
    }
};
