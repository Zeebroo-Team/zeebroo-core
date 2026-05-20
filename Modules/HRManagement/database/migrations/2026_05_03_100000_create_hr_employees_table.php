<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_employees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            $table->string('full_name');
            $table->date('date_of_birth')->nullable();
            $table->string('nic_passport_number', 64);
            $table->text('permanent_address');
            $table->text('current_address');
            $table->string('phone_number', 40);
            $table->string('personal_email', 255);

            $table->string('employee_id', 64);
            $table->string('job_title');
            $table->string('department');
            $table->date('date_of_joining');
            $table->string('employment_type', 24);

            $table->string('emergency_contact_name');
            $table->string('emergency_contact_relationship');
            $table->string('emergency_contact_phone', 40);

            $table->string('bank_account_holder_name');
            $table->string('bank_name');
            $table->string('bank_branch');
            $table->string('bank_account_number', 64);

            $table->string('epf_number', 80)->nullable();
            $table->string('etf_number', 80)->nullable();
            $table->string('tax_tin', 80)->nullable();

            $table->timestamps();

            $table->unique(['business_id', 'nic_passport_number']);
            $table->unique(['business_id', 'employee_id']);
            $table->index(['business_id', 'date_of_joining']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employees');
    }
};
