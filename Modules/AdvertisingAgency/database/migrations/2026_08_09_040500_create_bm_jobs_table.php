<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bm_jobs')) {
            return;
        }
        Schema::create('bm_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->string('name', 200);
            $table->unsignedBigInteger('client_brand_id')->nullable()->index();
            $table->unsignedBigInteger('officer_id')->nullable()->index();
            $table->unsignedBigInteger('reporter_id')->nullable()->index();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('pending');
            $table->date('start_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bm_jobs');
    }
};
