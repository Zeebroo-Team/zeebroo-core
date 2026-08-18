<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('em_ticket_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id')->index();
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('em_events')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('em_ticket_types');
    }
};
