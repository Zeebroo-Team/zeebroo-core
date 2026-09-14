<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bm_promoters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->string('name', 150);
            $table->string('position', 100)->nullable();
            $table->string('nic', 50)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('bank_name', 100);
            $table->string('bank_branch', 100);
            $table->string('bank_account', 50);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bm_promoters');
    }
};
