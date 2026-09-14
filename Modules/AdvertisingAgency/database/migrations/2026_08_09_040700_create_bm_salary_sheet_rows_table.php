<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bm_salary_sheet_rows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sheet_id')->index();
            $table->string('item_number', 30)->nullable();
            $table->string('location', 100)->nullable();
            $table->string('position', 100)->nullable();
            $table->unsignedBigInteger('promoter_id')->nullable()->index();
            $table->string('promoter_name', 150)->nullable();
            $table->decimal('daily_rate', 12, 2)->default(0);
            $table->decimal('transport_allowance', 12, 2)->default(0);
            $table->decimal('expenses', 12, 2)->default(0);
            $table->decimal('hold_amount', 12, 2)->default(0);
            $table->unsignedBigInteger('coordinator_id')->nullable()->index();
            $table->string('coordinator_name', 150)->nullable();
            $table->decimal('coordination_fee', 12, 2)->default(0);
            $table->string('coordinator_bank_details', 250)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('sheet_id')
                  ->references('id')->on('bm_salary_sheets')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bm_salary_sheet_rows');
    }
};
