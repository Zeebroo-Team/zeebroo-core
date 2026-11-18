<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The user's CRM profile in this business — printed as "Prepared by" on quotations.
        // Empty = taken from their HR employee record (name, job title, phone).
        Schema::table('crm_user_preferences', function (Blueprint $table) {
            $table->string('display_name', 120)->nullable()->after('email_notifications');
            $table->string('designation', 120)->nullable()->after('display_name');
            $table->string('phone', 40)->nullable()->after('designation');
        });
    }

    public function down(): void
    {
        Schema::table('crm_user_preferences', function (Blueprint $table) {
            $table->dropColumn(['display_name', 'designation', 'phone']);
        });
    }
};
