<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('design_studio_designs', function (Blueprint $table) {
            if (! Schema::hasColumn('design_studio_designs', 'quotation_id')) {
                $table->unsignedBigInteger('quotation_id')->nullable()->after('invoice_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('design_studio_designs', function (Blueprint $table) {
            $table->dropColumn('quotation_id');
        });
    }
};
