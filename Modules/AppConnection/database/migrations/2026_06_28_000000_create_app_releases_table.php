<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_releases', function (Blueprint $table): void {
            $table->id();
            $table->string('version', 32)->unique();
            $table->date('release_date');
            $table->string('channel', 16)->default('stable'); // stable | beta | alpha | rc
            $table->boolean('is_latest')->default(false);
            $table->json('notes')->nullable();
            $table->string('windows_url')->nullable();
            $table->string('macos_url')->nullable();
            $table->string('linux_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_releases');
    }
};
