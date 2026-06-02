<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('pos_sale_items', function (Blueprint $table) {
            $table->string('selling_unit_label', 80)->nullable()->after('sku');
            $table->decimal('selling_unit_factor', 10, 6)->nullable()->after('selling_unit_label');
        });
    }
    public function down(): void {
        Schema::table('pos_sale_items', function (Blueprint $table) {
            $table->dropColumn(['selling_unit_label', 'selling_unit_factor']);
        });
    }
};
