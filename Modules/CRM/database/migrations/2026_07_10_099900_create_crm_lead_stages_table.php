<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_lead_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('crm_projects')->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('color', 20)->nullable();
            $table->boolean('is_won')->default(false);
            $table->boolean('is_lost')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['project_id', 'name']);
            $table->index(['project_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_lead_stages');
    }
};
