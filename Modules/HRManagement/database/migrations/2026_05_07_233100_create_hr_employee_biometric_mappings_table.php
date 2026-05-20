<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_employee_biometric_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->string('device_id', 100);
            $table->string('device_employee_code', 120);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['business_id', 'device_id', 'device_employee_code'],
                'hr_bio_mapping_unique_device_code_per_business'
            );
            $table->index(['business_id', 'employee_id'], 'hr_bio_mapping_business_employee_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employee_biometric_mappings');
    }
};
