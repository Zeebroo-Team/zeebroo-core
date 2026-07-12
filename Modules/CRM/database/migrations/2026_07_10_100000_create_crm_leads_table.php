<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('crm_projects')->cascadeOnDelete();
            $table->string('name');
            $table->string('company', 150)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('source', 60)->nullable();
            $table->foreignId('stage_id')->nullable()->constrained('crm_lead_stages')->nullOnDelete();
            $table->decimal('estimated_value', 14, 2)->nullable();
            $table->date('expected_close_date')->nullable();
            $table->string('lost_reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('pos_customers')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'stage_id']);
            $table->index(['project_id', 'stage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_leads');
    }
};
