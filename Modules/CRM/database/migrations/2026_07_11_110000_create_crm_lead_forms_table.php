<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_lead_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('crm_projects')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('token', 32)->unique();
            $table->json('blocks')->nullable();
            $table->json('style')->nullable();
            $table->string('submit_button_text', 60)->default('Submit');
            $table->text('success_message')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index(['project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_lead_forms');
    }
};
