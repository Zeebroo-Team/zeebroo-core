<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One online store (public website) per business.
        Schema::create('ecom_stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained('businesses')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('slug', 80)->unique();
            $table->string('tagline', 200)->nullable();
            $table->text('about')->nullable();
            $table->string('logo_url', 500)->nullable();
            $table->string('banner_url', 500)->nullable();
            $table->string('theme_color', 20)->default('#7c3aed');
            $table->string('contact_email', 150)->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('currency', 10)->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('cod_enabled')->default(true);
            $table->boolean('bank_transfer_enabled')->default(false);
            $table->text('bank_transfer_details')->nullable();
            $table->decimal('shipping_flat_rate', 14, 2)->default(0);
            $table->decimal('free_shipping_over', 14, 2)->nullable();
            $table->decimal('min_order_amount', 14, 2)->nullable();
            $table->string('announcement', 255)->nullable();
            $table->json('social_links')->nullable();
            $table->string('seo_title', 160)->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecom_stores');
    }
};
