<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_payroll_rule_sets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name', 140);
            $table->string('currency', 16)->default('LKR');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'is_active']);
            $table->index(['business_id', 'effective_from']);
        });

        Schema::create('hr_payroll_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rule_set_id')->constrained('hr_payroll_rule_sets')->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('name', 140);
            $table->string('component_type', 32);
            $table->string('calculation_mode', 24)->default('fixed');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_taxable')->default(false);
            $table->boolean('is_statutory')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('config_json')->nullable();
            $table->timestamps();

            $table->unique(['rule_set_id', 'code']);
            $table->index(['rule_set_id', 'component_type']);
        });

        Schema::create('hr_payroll_cycles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rule_set_id')->nullable()->constrained('hr_payroll_rule_sets')->nullOnDelete();
            $table->string('name', 140);
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 24)->default('draft');
            $table->timestamp('computed_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['business_id', 'year', 'month']);
            $table->index(['business_id', 'status']);
        });

        Schema::create('hr_payroll_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_cycle_id')->constrained('hr_payroll_cycles')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->string('status', 24)->default('computed');
            $table->decimal('basic_salary', 14, 2)->default(0);
            $table->decimal('overtime_amount', 14, 2)->default(0);
            $table->decimal('gross_earnings', 14, 2)->default(0);
            $table->decimal('total_deductions', 14, 2)->default(0);
            $table->decimal('net_pay', 14, 2)->default(0);
            $table->json('inputs_json')->nullable();
            $table->json('snapshot_json')->nullable();
            $table->timestamps();

            $table->unique(['payroll_cycle_id', 'employee_id']);
            $table->index(['payroll_cycle_id', 'status']);
        });

        Schema::create('hr_payroll_item_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_item_id')->constrained('hr_payroll_items')->cascadeOnDelete();
            $table->foreignId('rule_id')->nullable()->constrained('hr_payroll_rules')->nullOnDelete();
            $table->string('code', 64);
            $table->string('name', 140);
            $table->string('component_type', 32);
            $table->decimal('quantity', 14, 4)->default(0);
            $table->decimal('rate', 14, 4)->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index(['payroll_item_id', 'component_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_payroll_item_components');
        Schema::dropIfExists('hr_payroll_items');
        Schema::dropIfExists('hr_payroll_cycles');
        Schema::dropIfExists('hr_payroll_rules');
        Schema::dropIfExists('hr_payroll_rule_sets');
    }
};
