<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Affiliate settings: contact, address, payout bank account and ID details.
        // account_number and id_number are encrypted by the model (text columns).
        Schema::create('affiliate_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();

            $table->string('phone', 30)->nullable();
            $table->string('phone_alt', 30)->nullable();

            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 100)->nullable();

            $table->string('bank_name', 120)->nullable();
            $table->string('bank_branch', 120)->nullable();
            $table->string('account_holder_name')->nullable();
            $table->text('account_number')->nullable();

            $table->string('id_type', 20)->nullable();
            $table->text('id_number')->nullable();

            $table->timestamps();
        });

        // ID card (front/back) or passport copies, kept on the private disk.
        Schema::create('affiliate_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();

            $table->unique(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_documents');
        Schema::dropIfExists('affiliate_profiles');
    }
};
