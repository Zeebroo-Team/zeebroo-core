<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->boolean('is_settled')->default(true)->after('status');
            $table->timestamp('settled_at')->nullable()->after('is_settled');
            $table->index(['business_id', 'is_settled']);
        });
    }
    public function down(): void {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'is_settled']);
            $table->dropColumn(['is_settled', 'settled_at']);
        });
    }
};
