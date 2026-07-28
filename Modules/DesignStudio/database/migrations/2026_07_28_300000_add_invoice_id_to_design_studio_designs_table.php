<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('design_studio_designs', function (Blueprint $table) {
            if (! Schema::hasColumn('design_studio_designs', 'invoice_id')) {
                $table->unsignedBigInteger('invoice_id')->nullable()->after('proposal_sort')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('design_studio_designs', function (Blueprint $table) {
            $table->dropColumn('invoice_id');
        });
    }
};
