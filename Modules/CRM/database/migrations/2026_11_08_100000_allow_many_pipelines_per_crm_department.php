<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A department can now own several pipelines; the first linked is its main one.
        // The plain index goes in first so the foreign keys never lose their index.
        Schema::table('crm_projects', function (Blueprint $table) {
            $table->index(['business_id', 'department_id']);
        });
        Schema::table('crm_projects', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'department_id']);
        });
    }

    public function down(): void
    {
        // Fails if a department still has more than one pipeline — unlink the extras first
        Schema::table('crm_projects', function (Blueprint $table) {
            $table->unique(['business_id', 'department_id']);
        });
        Schema::table('crm_projects', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'department_id']);
        });
    }
};
