<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('field', 20); // from | subject
            $table->string('value', 190);
            $table->string('action', 20); // mark_read | delete
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['business_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_filters');
    }
};
