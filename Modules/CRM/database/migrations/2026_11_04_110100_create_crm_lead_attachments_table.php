<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Files on a lead. The file itself is a FileManager file (folder "CRM/Leads/<lead id>",
        // private disk); it is only ever served through the CRM's authorised download route.
        Schema::create('crm_lead_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained('crm_leads')->cascadeOnDelete();
            // The FileManager module always migrates first in an install; CRM-only test schemas
            // have no file_manager_files, and SQLite rejects a cascade into a missing table.
            if (Schema::hasTable('file_manager_files')) {
                $table->foreignId('file_manager_file_id')->constrained('file_manager_files')->cascadeOnDelete();
            } else {
                $table->unsignedBigInteger('file_manager_file_id')->index();
            }
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_image')->default(false);
            $table->timestamps();

            $table->index(['lead_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_lead_attachments');
    }
};
