<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bm_salary_sheet_position_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sheet_id')->index();
            $table->string('position_name', 150);
            $table->decimal('daily_rate', 12, 2)->default(0);
            $table->decimal('transport_allowance', 12, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('sheet_id')
                  ->references('id')->on('bm_salary_sheets')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bm_salary_sheet_position_rules');
    }
};
