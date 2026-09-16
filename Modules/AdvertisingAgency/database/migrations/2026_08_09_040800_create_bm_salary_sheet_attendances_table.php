<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bm_salary_sheet_attendances')) {
            return;
        }
        Schema::create('bm_salary_sheet_attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('row_id')->index();
            $table->date('attendance_date');
            $table->string('attendance_status', 1);
            $table->timestamps();

            $table->foreign('row_id')
                  ->references('id')->on('bm_salary_sheet_rows')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bm_salary_sheet_attendances');
    }
};
