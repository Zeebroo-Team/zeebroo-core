<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The user's signature image (private "local" disk) — printed in "Approved by" on quotations they approve.
        Schema::table('crm_user_preferences', function (Blueprint $table) {
            $table->string('signature_path', 255)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('crm_user_preferences', function (Blueprint $table) {
            $table->dropColumn('signature_path');
        });
    }
};
