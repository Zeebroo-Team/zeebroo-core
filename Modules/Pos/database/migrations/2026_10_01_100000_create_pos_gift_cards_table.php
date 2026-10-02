<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_gift_cards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('pos_customer_id')->nullable()->constrained('pos_customers')->nullOnDelete();
            $table->string('name', 191);
            $table->string('code', 40);
            $table->decimal('initial_value', 12, 2);
            // Remaining spendable amount — decremented on each redemption, so a
            // card can be used across several sales until it reaches zero.
            $table->decimal('balance', 12, 2);
            $table->date('valid_from')->nullable();
            $table->date('expires_at')->nullable(); // null = never expires
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['business_id', 'code']);
            $table->index(['business_id', 'is_active']);
        });

        Schema::create('pos_gift_card_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pos_gift_card_id')->constrained('pos_gift_cards')->cascadeOnDelete();
            $table->foreignId('pos_sale_id')->nullable()->constrained('pos_sales')->nullOnDelete();
            $table->string('type', 16); // issue | redeem | refund | adjust
            // Signed: positive adds to the balance, negative spends from it.
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('notes', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['pos_gift_card_id', 'created_at']);
        });

        Schema::table('pos_sales', function (Blueprint $table): void {
            $table->foreignId('pos_gift_card_id')->nullable()->after('pos_customer_id')
                  ->constrained('pos_gift_cards')->nullOnDelete();
            // Portion of `total` paid by gift card; the rest is paid via payment_method.
            $table->decimal('gift_card_amount', 12, 2)->default(0)->after('amount_paid');
        });
    }

    public function down(): void
    {
        Schema::table('pos_sales', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pos_gift_card_id');
            $table->dropColumn('gift_card_amount');
        });
        Schema::dropIfExists('pos_gift_card_transactions');
        Schema::dropIfExists('pos_gift_cards');
    }
};
