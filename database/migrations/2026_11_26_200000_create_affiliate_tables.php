<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        // Codes an affiliate marketer generates and shares (/register?ref=CODE).
        Schema::create('affiliate_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('code', 32)->unique();
            $table->string('label', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // One row per account that signed up with a code. A user can only ever be
        // referred once (unique referred_user_id). `code` keeps the code as typed
        // at sign-up so the history survives if the code row is later removed.
        Schema::create('affiliate_referrals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('affiliate_code_id')->nullable()->constrained('affiliate_codes')->nullOnDelete();
            $table->foreignId('referred_user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('platform', 20)->default('web');
            $table->timestamps();

            $table->index(['affiliate_user_id', 'created_at']);
        });

        // The login role for affiliate sales marketers (created by an admin).
        Role::firstOrCreate(['name' => 'affiliate', 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_referrals');
        Schema::dropIfExists('affiliate_codes');

        Role::query()->where('name', 'affiliate')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
