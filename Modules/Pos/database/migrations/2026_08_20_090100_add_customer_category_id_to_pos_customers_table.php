<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_customers', function (Blueprint $table): void {
            $table->foreignId('customer_category_id')->nullable()->after('customer_type')
                ->constrained('pos_customer_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pos_customers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_category_id');
        });
    }
};
