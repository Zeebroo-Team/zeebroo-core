<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email verification by 6-digit code for self-service sign-ups. Only accounts that
 * register while the "Email verification" automated email is on get the flag, so
 * existing users, employees and admin-created accounts are never locked out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('email_verification_required')->default(false)->after('email_verified_at');
            $table->string('email_verification_code')->nullable()->after('email_verification_required');
            $table->timestamp('email_verification_sent_at')->nullable()->after('email_verification_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['email_verification_required', 'email_verification_code', 'email_verification_sent_at']);
        });
    }
};
