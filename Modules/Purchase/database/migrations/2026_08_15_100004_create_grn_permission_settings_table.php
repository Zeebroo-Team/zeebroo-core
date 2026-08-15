<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grn_permission_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->unique();
            // 'without_permission' | 'approval_processing'
            $table->string('approval_mode', 30)->default('without_permission');
            // JSON: { "admin": { "create": true, "read": true, "approval": true }, … }
            $table->json('role_permissions')->nullable();
            $table->timestamps();

            $table->foreign('business_id')
                  ->references('id')
                  ->on('businesses')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grn_permission_settings');
    }
};
