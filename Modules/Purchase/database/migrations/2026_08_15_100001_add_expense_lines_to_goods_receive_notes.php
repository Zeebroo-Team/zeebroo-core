<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receive_notes', function (Blueprint $table) {
            // Stores expense rules applied to this GRN, e.g.
            // [{"name":"Shipping","type":"flat","value":50},{"name":"Handling","type":"pct","value":2}]
            $table->json('expense_lines')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receive_notes', function (Blueprint $table) {
            $table->dropColumn('expense_lines');
        });
    }
};
