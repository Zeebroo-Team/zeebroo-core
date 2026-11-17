<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CRM-only places in a department on top of the HR record: a Sales employee who also works
        // IT leads ("member"), or someone who heads IT as well as their own department ("head")
        Schema::create('crm_department_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('hr_departments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // member | head (see DepartmentMember)
            $table->string('role', 20)->default('member');
            $table->timestamps();

            $table->unique(['department_id', 'user_id']);
            $table->index(['business_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_department_members');
    }
};
