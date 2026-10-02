<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A coupon is one discount offer with ONE shared code (e.g. "AVURUDU10").
        // `quantity` is how many times that code can be redeemed in total.
        Schema::create('pos_coupons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name', 191);
            $table->string('code', 40);
            $table->string('discount_type', 10); // percent | flat
            $table->decimal('discount_value', 12, 2);
            $table->unsignedInteger('quantity');            // total redemptions allowed
            $table->unsignedInteger('used_count')->default(0);
            $table->date('valid_from')->nullable();
            $table->date('expires_at')->nullable(); // null = never expires
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['business_id', 'code']);
            $table->index(['business_id', 'is_active']);
        });

        Schema::create('pos_coupon_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pos_coupon_id')->constrained('pos_coupons')->cascadeOnDelete();
            $table->foreignId('pos_sale_id')->nullable()->constrained('pos_sales')->nullOnDelete();
            $table->decimal('discount_amount', 12, 2);
            // Set when the sale is voided — the use is handed back to the coupon.
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['pos_coupon_id', 'created_at']);
        });

        Schema::table('pos_sales', function (Blueprint $table): void {
            $table->foreignId('pos_coupon_id')->nullable()->after('pos_gift_card_id')
                  ->constrained('pos_coupons')->nullOnDelete();
            // Coupon discount, taken off after the order discount: total = subtotal - discount_amount - coupon_discount.
            $table->decimal('coupon_discount', 12, 2)->default(0)->after('discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('pos_sales', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pos_coupon_id');
            $table->dropColumn('coupon_discount');
        });
        Schema::dropIfExists('pos_coupon_redemptions');
        Schema::dropIfExists('pos_coupons');
    }
};
