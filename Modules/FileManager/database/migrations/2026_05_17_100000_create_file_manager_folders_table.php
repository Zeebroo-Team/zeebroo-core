<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_manager_folders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('file_manager_folders')->cascadeOnDelete();
            $table->string('name', 120);
            $table->timestamps();

            $table->index(['business_id', 'parent_id']);
            $table->unique(['business_id', 'parent_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_manager_folders');
    }
};
