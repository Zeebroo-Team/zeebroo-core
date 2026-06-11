<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('design_studio_designs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120)->default('Untitled Design');
            $table->unsignedSmallInteger('width')->default(1080);
            $table->unsignedSmallInteger('height')->default(1080);
            $table->longText('canvas_json')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_studio_designs');
    }
};
