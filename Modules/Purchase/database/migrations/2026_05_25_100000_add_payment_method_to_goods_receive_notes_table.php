<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receive_notes', function (Blueprint $table) {
            $table->string('payment_method', 20)->nullable()->after('total');
            $table->string('payment_reference', 120)->nullable()->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receive_notes', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'payment_reference']);
        });
    }
};
