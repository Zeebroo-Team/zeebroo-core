<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('flow_id')->nullable();
            $table->string('title')->nullable();
            $table->text('message');
            $table->json('payload')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'read_at']);
            $table->index('flow_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_notifications');
    }
};
