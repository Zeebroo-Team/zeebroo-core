<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_allowance_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['business_id', 'name']);
            $table->index(['business_id', 'sort_order']);
        });

        Schema::table('hr_employees', function (Blueprint $table): void {
            $table->decimal('basic_salary', 15, 2)->nullable()->after('employment_type');
            $table->decimal('salary', 15, 2)->nullable()->after('basic_salary');
        });

        Schema::create('hr_employee_allowances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('allowance_type_id')->constrained('hr_allowance_types')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->unique(['employee_id', 'allowance_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employee_allowances');

        Schema::table('hr_employees', function (Blueprint $table): void {
            $table->dropColumn(['basic_salary', 'salary']);
        });

        Schema::dropIfExists('hr_allowance_types');
    }
};
