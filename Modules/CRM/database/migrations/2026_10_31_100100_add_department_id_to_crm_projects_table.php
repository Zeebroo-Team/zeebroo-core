<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One pipeline per HR department; pipelines without a department keep working as before
        Schema::table('crm_projects', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('business_id')
                ->constrained('hr_departments')->nullOnDelete();

            $table->unique(['business_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::table('crm_projects', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'department_id']);
            $table->dropConstrainedForeignId('department_id');
        });
    }
};
