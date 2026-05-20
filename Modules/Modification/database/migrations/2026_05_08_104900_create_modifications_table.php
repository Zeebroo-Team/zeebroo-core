<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('assignment_type', 40)->default('renovation');
            $table->string('assignment_reference')->nullable();
            $table->decimal('estimated_cost', 14, 2)->default(0);
            $table->string('duration')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'created_at']);
            $table->index(['business_id', 'assignment_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modifications');
    }
};
