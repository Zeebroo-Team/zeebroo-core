<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_flows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(false);
            $table->string('trigger_type')->nullable(); // e.g. sale.created
            $table->json('flow_data')->nullable();      // full canvas JSON
            $table->unsignedBigInteger('run_count')->default(0);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->index('business_id');
            $table->index(['business_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_flows');
    }
};
