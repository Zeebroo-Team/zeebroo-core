<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per credit sale a payment was applied to; rows from one receipt share a `reference`.
        Schema::create('pos_credit_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('pos_customer_id')->nullable()->constrained('pos_customers')->nullOnDelete();
            $table->foreignId('pos_sale_id')->constrained('pos_sales')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference', 40);
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 20)->default('cash');
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->index(['business_id', 'pos_customer_id', 'paid_at']);
            $table->index('reference');
        });

        // Credit payments taken before this table existed only live in pos_sales.amount_paid
        // (minus any gift card used at checkout) — keep them visible in the payment history.
        DB::table('pos_sales')
            ->where('payment_method', 'credit')
            ->whereRaw('amount_paid - COALESCE(gift_card_amount, 0) > 0.005')
            ->orderBy('id')
            ->get(['id', 'business_id', 'pos_customer_id', 'amount_paid', 'gift_card_amount', 'updated_at'])
            ->each(fn ($s) => DB::table('pos_credit_payments')->insert([
                'business_id'     => $s->business_id,
                'pos_customer_id' => $s->pos_customer_id,
                'pos_sale_id'     => $s->id,
                'user_id'         => null,
                'reference'       => 'CP-EARLIER-'.$s->id,
                'amount'          => round((float) $s->amount_paid - (float) ($s->gift_card_amount ?? 0), 2),
                'payment_method'  => 'cash',
                'paid_at'         => $s->updated_at ?? now(),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_credit_payments');
    }
};
