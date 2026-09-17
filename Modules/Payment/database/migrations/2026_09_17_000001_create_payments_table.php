<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained()->nullOnDelete();

            // 'subscription' (paid, recurring monthly package) or 'free' (no charge required).
            $table->string('payment_type')->default('subscription');
            // pending | processing | succeeded | failed | canceled | refunded
            $table->string('payment_status')->default('pending');
            // Recurring interval this payment represents — always 'monthly' today.
            $table->string('billing_cycle')->nullable()->default('monthly');

            $table->string('gateway')->default('stripe');
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('usd');

            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_checkout_session_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('stripe_invoice_id')->nullable();
            // Mirrors the Stripe subscription lifecycle: active | past_due | canceled | unpaid | incomplete | trialing.
            $table->string('stripe_subscription_status')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index('payment_status');
            $table->index('stripe_checkout_session_id');
            $table->index('stripe_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
