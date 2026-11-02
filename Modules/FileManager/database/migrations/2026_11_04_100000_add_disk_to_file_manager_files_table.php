<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // disk: where the file is stored (null = the public disk, as before).
        // managed_by: the module that owns the file (e.g. "crm") and serves it through its own
        // authorised route — the file manager lists it but does not download or delete it.
        Schema::table('file_manager_files', function (Blueprint $table): void {
            $table->string('disk', 40)->nullable()->after('stored_path');
            $table->string('managed_by', 40)->nullable()->after('disk');
        });
    }

    public function down(): void
    {
        Schema::table('file_manager_files', function (Blueprint $table): void {
            $table->dropColumn(['disk', 'managed_by']);
        });
    }
};
