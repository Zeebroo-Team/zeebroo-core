<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_counters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('name', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');
        });

        Schema::table('pos_sales', function (Blueprint $table) {
            $table->unsignedBigInteger('pos_counter_id')->nullable()->after('branch_id');
        });
    }

    public function down(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->dropColumn('pos_counter_id');
        });
        Schema::dropIfExists('pos_counters');
    }
};
