<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aa_ad_creatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('aa_campaigns')->cascadeOnDelete();
            $table->string('title');
            $table->string('format', 40)->default('image');
            $table->string('headline', 255)->nullable();
            $table->text('body_copy')->nullable();
            $table->string('call_to_action', 100)->nullable();
            $table->string('file_url', 500)->nullable();
            $table->string('file_name', 255)->nullable();
            $table->string('dimensions', 40)->nullable();
            $table->string('status', 30)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aa_ad_creatives');
    }
};
