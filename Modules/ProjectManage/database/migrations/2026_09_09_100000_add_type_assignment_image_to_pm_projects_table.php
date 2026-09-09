<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_projects', function (Blueprint $table): void {
            $table->string('project_type', 20)->default('in_house')->after('client_name');
            $table->foreignId('customer_id')->nullable()->after('project_type')->constrained('pos_customers')->nullOnDelete();

            $table->string('assignment_type', 20)->default('none')->after('customer_id');
            $table->foreignId('branch_id')->nullable()->after('assignment_type')->constrained('branches')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->after('branch_id')->constrained('hr_departments')->nullOnDelete();
            $table->foreignId('property_id')->nullable()->after('department_id')->constrained('properties')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->after('property_id')->constrained('hr_employees')->nullOnDelete();
            $table->foreignId('modification_id')->nullable()->after('employee_id')->constrained('modifications')->nullOnDelete();
            $table->foreignId('rental_id')->nullable()->after('modification_id')->constrained('rentals')->nullOnDelete();
            $table->string('assignment_reference', 255)->nullable()->after('rental_id');

            $table->unsignedBigInteger('file_manager_file_id')->nullable()->after('assignment_reference');
        });
    }

    public function down(): void
    {
        Schema::table('pm_projects', function (Blueprint $table): void {
            $table->dropColumn('file_manager_file_id');
            $table->dropColumn('assignment_reference');
            $table->dropConstrainedForeignId('rental_id');
            $table->dropConstrainedForeignId('modification_id');
            $table->dropConstrainedForeignId('employee_id');
            $table->dropConstrainedForeignId('property_id');
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn('assignment_type');
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn('project_type');
        });
    }
};
