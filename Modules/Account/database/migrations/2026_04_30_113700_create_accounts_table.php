<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('account_name');
            $table->foreignId('bank_type_id')->constrained('bank_types')->restrictOnDelete();
            $table->string('bank_name');
            $table->string('bank_account_number');
            $table->string('branch');
            $table->string('bank_officer_contact')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
