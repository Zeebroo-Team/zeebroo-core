<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receive_notes', function (Blueprint $table) {
            // Credit payment terms: number of days until payment is due
            $table->unsignedSmallInteger('payment_terms_days')->nullable()->after('cheque_due_date');
            // Computed: received_date + payment_terms_days
            $table->date('payment_due_date')->nullable()->after('payment_terms_days');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receive_notes', function (Blueprint $table) {
            $table->dropColumn(['payment_terms_days', 'payment_due_date']);
        });
    }
};
