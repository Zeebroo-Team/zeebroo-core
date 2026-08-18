<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('em_attendees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('ticket_type_id')->nullable()->index();
            $table->string('name', 150);
            $table->string('email', 150)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('notes', 500)->nullable();
            $table->dateTime('checked_in_at')->nullable();
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('em_events')->cascadeOnDelete();
            $table->foreign('ticket_type_id')->references('id')->on('em_ticket_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('em_attendees');
    }
};
