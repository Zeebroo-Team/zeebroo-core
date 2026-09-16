<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bm_salary_sheets')) {
            return;
        }
        Schema::create('bm_salary_sheets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('job_id')->nullable()->index();
            $table->string('sheet_ref', 50);
            $table->string('title', 200)->nullable();
            $table->date('date_from');
            $table->date('date_to');
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('job_id')
                  ->references('id')->on('bm_jobs')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bm_salary_sheets');
    }
};
