<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_manager_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained('file_manager_folders')->nullOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_filename', 255);
            $table->string('stored_path', 500);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'folder_id']);
            $table->index(['business_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_manager_files');
    }
};
