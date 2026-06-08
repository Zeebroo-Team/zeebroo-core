<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receive_notes', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete()->after('business_id');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receive_notes', function (Blueprint $table): void {
            $table->dropForeignIdFor(\Modules\Business\Models\Branch::class);
            $table->dropColumn('branch_id');
        });
    }
};
