<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A business without the HR feature still needs employee rows for the CRM (department, head,
 * login). Those rows come from Settings → Users with only a name, email and department, so the
 * HR-only columns may be empty until HR completes the profile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_employees', function (Blueprint $table): void {
            $table->string('nic_passport_number', 64)->nullable()->change();
            $table->text('permanent_address')->nullable()->change();
            $table->text('current_address')->nullable()->change();
            $table->string('phone_number', 40)->nullable()->change();
            $table->string('employment_type', 24)->nullable()->change();
            $table->unsignedBigInteger('job_title_id')->nullable()->change();
            $table->string('emergency_contact_name')->nullable()->change();
            $table->string('emergency_contact_relationship')->nullable()->change();
            $table->string('emergency_contact_phone', 40)->nullable()->change();
            $table->string('bank_account_holder_name')->nullable()->change();
            $table->unsignedBigInteger('bank_id')->nullable()->change();
            $table->string('bank_branch')->nullable()->change();
            $table->string('bank_account_number', 64)->nullable()->change();

            $table->boolean('profile_complete')->default(true)->after('user_id');
        });
    }

    /** The columns stay nullable: incomplete profiles may already hold nulls. */
    public function down(): void
    {
        Schema::table('hr_employees', function (Blueprint $table): void {
            $table->dropColumn('profile_complete');
        });
    }
};
