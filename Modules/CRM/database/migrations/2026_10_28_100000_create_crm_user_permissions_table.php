<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_user_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('user_id');
            // JSON: { "leads_create": false, "stages_manage": false, … } — a missing key means allowed
            $table->json('permissions')->nullable();
            // JSON: { "<project_id>": [<stage_id>, …] } — a missing relation means every stage is
            // visible; an empty list hides the relation from this user entirely
            $table->json('stage_access')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'user_id']);

            $table->foreign('business_id')
                  ->references('id')
                  ->on('businesses')
                  ->cascadeOnDelete();
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_user_permissions');
    }
};
