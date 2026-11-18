<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A lead quotation whose total is at or below this (and whose discount is within
        // discount_approval_limit_percent) is issued without the head's approval. Null = every
        // quotation needs approval.
        Schema::table('crm_department_settings', function (Blueprint $table) {
            $table->decimal('quotation_value_approval_limit', 15, 2)->nullable()->after('discount_approval_limit_percent');
        });
    }

    public function down(): void
    {
        Schema::table('crm_department_settings', function (Blueprint $table) {
            $table->dropColumn('quotation_value_approval_limit');
        });
    }
};
