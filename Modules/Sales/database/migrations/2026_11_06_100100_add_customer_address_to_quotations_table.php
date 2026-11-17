<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The address the quotation is billed to — filled from the customer when there is one,
        // and editable per quotation (a lead not yet a customer has none on file)
        Schema::table('quotations', function (Blueprint $table) {
            $table->text('customer_address')->nullable()->after('lead_id');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('customer_address');
        });
    }
};
