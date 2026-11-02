<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CRM-only sub-units of an HR department (e.g. "Solar" / "Batteries" inside Sales)
        Schema::create('crm_divisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('hr_departments')->cascadeOnDelete();
            $table->string('name', 150);
            // Overrides the department head for leads in this division
            $table->foreignId('head_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['department_id', 'name']);
            $table->index(['business_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_divisions');
    }
};
