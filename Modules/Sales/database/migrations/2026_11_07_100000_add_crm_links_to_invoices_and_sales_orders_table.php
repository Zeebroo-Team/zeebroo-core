<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which won CRM lead / accepted quotation an invoice or sales order was made from
        foreach (['invoices', 'sales_orders'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
                $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['invoices', 'sales_orders'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('lead_id');
                $table->dropConstrainedForeignId('quotation_id');
            });
        }
    }
};
