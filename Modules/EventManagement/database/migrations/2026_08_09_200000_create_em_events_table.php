<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('em_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('venue', 255)->nullable();
            $table->dateTime('start_at')->nullable()->index();
            $table->dateTime('end_at')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->string('category', 50)->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('em_events');
    }
};
