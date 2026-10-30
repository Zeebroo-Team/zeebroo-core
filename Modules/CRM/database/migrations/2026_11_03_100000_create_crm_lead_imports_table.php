<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A bulk lead upload (our CSV template, a Google Forms responses export or a
        // Facebook Lead Ads export): the stored file, the column mapping and the outcome.
        Schema::create('crm_lead_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('crm_projects')->nullOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('format', 20);
            $table->string('source', 40);
            $table->json('mapping')->nullable();
            $table->json('options')->nullable();
            $table->string('status', 20);
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->longText('error_rows')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'created_at']);
        });

        // One row per imported file row, so uploading the same export again (Google Forms and
        // Facebook exports always hold every response so far) skips what is already in.
        Schema::create('crm_lead_import_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->char('row_key', 40);
            $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();
            $table->foreignId('import_id')->nullable()->constrained('crm_lead_imports')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['business_id', 'row_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_lead_import_keys');
        Schema::dropIfExists('crm_lead_imports');
    }
};
