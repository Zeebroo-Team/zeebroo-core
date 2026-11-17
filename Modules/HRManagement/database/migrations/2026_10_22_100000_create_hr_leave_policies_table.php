<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_leave_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('annual_leave_days')->default(14);
            $table->unsignedSmallInteger('casual_leave_days')->default(7);
            $table->unsignedSmallInteger('sick_leave_days')->nullable();
            $table->boolean('short_leave_enabled')->default(false);
            $table->unsignedTinyInteger('short_leaves_per_month')->nullable();
            $table->unsignedSmallInteger('short_leave_max_minutes')->nullable();
            $table->boolean('half_day_allowed')->default(true);
            $table->unsignedSmallInteger('carry_forward_max_days')->nullable();
            $table->unsignedTinyInteger('eligible_after_months')->default(0);
            $table->boolean('is_default')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_leave_policies');
    }
};
