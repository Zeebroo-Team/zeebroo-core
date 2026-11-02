<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A file shared inside an internal message (it still shows under the lead's Files).
        // No FK: SQLite cannot add one to an existing table; LeadMessageService clears it when
        // the message is deleted, and the file stays on the lead.
        Schema::table('crm_lead_attachments', function (Blueprint $table) {
            $table->unsignedBigInteger('message_id')->nullable()->after('lead_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('crm_lead_attachments', function (Blueprint $table) {
            $table->dropIndex(['message_id']);
            $table->dropColumn('message_id');
        });
    }
};
