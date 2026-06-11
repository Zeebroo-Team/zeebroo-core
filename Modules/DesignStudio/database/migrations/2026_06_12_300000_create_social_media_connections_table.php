<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_media_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('platform', 30);           // facebook, linkedin, youtube, tiktok
            $table->string('external_id', 150);       // page_id, channel_id, etc.
            $table->string('name', 255);              // page / channel name
            $table->string('picture_url', 512)->nullable();
            $table->text('access_token');             // stored encrypted
            $table->timestamp('token_expires_at')->nullable();
            $table->json('metadata')->nullable();     // category, fan_count, etc.
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['business_id', 'platform', 'external_id']);
            $table->index(['business_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_media_connections');
    }
};
