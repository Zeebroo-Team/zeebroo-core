<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_department_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->unique()->constrained('hr_departments')->cascadeOnDelete();
            // Only one department per business may be the intake (enforced in CrmOrgService)
            $table->boolean('is_marketing_intake')->default(false);
            $table->decimal('discount_approval_limit_percent', 5, 2)->nullable();
            // JSON: [2, 5] — days after a quotation is sent. MySQL json columns can't take a
            // literal default, so null means the default from DepartmentSetting.
            $table->json('followup_days')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'is_marketing_intake']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_department_settings');
    }
};
