<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('address_books', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('street_address')->nullable();
            $table->text('bank_account_details')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            /** Non-null email/phone uniqueness is enforced in app (SQLite allows duplicate NULL composites). */
            $table->index(['user_id', 'email']);
            $table->index(['user_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('address_books');
    }
};
