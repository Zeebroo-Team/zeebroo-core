<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_feature_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('reviewer_key', 80);
            $table->string('reviewer_name', 150);
            $table->string('feature_key', 60);
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');
            $table->unique(['feature_key', 'reviewer_key']);
            $table->index('feature_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_feature_reviews');
    }
};
