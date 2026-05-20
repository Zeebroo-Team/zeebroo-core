<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('scope_type');
            $table->unsignedBigInteger('scope_id');
            $table->string('key');
            $table->text('value')->nullable();
            $table->string('value_type')->default('string');
            $table->timestamps();

            $table->index(['scope_type', 'scope_id']);
            $table->unique(['scope_type', 'scope_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
