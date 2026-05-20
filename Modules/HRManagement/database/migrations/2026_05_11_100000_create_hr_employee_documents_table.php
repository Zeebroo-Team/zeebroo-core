<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_employee_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->string('category', 40);
            $table->string('original_filename', 255);
            $table->string('stored_path', 512);
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'category']);
            $table->index(['business_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employee_documents');
    }
};
