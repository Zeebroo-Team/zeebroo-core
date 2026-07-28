<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('flow_id');
            $table->string('status'); // success | failed | skipped
            $table->json('trigger_payload')->nullable();
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('flow_id');
            $table->index(['flow_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_runs');
    }
};
