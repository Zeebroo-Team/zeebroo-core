<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_business_holidays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('holiday_date');
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'holiday_date']);
            $table->index(['business_id', 'holiday_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_business_holidays');
    }
};
