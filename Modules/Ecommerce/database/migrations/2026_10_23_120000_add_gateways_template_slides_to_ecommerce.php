<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Website template (storefront design).
        Schema::table('ecom_stores', function (Blueprint $table) {
            $table->string('template', 30)->default('classic')->after('theme_color');
        });

        // Online payment gateways — one row per business + gateway. Credentials are encrypted.
        Schema::create('ecom_payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('gateway', 30);                    // stripe|payhere
            $table->boolean('is_enabled')->default(false);
            $table->boolean('is_sandbox')->default(true);
            $table->text('credentials')->nullable();           // encrypted JSON
            $table->timestamps();

            $table->unique(['business_id', 'gateway']);
        });

        // Homepage hero slider.
        Schema::create('ecom_slides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('image_url', 500);
            $table->string('title', 150)->nullable();
            $table->string('subtitle', 255)->nullable();
            $table->string('button_text', 40)->nullable();
            $table->string('button_link', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['business_id', 'is_active', 'sort_order']);
        });

        // Online-payment tracking on orders.
        Schema::table('ecom_orders', function (Blueprint $table) {
            $table->string('access_token', 64)->nullable()->unique()->after('order_no');
            $table->string('payment_reference', 191)->nullable()->after('payment_status');
            $table->timestamp('paid_at')->nullable()->after('payment_reference');
        });
    }

    public function down(): void
    {
        Schema::table('ecom_orders', function (Blueprint $table) {
            $table->dropUnique(['access_token']);
            $table->dropColumn(['access_token', 'payment_reference', 'paid_at']);
        });
        Schema::dropIfExists('ecom_slides');
        Schema::dropIfExists('ecom_payment_gateways');
        Schema::table('ecom_stores', function (Blueprint $table) {
            $table->dropColumn('template');
        });
    }
};
