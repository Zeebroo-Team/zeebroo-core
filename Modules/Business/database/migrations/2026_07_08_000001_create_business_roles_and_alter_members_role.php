<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('slug', 60);
            $table->string('color', 20)->default('#64748b');
            $table->string('description', 255)->nullable();
            $table->json('permissions')->nullable();  // null = all permissions
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['business_id', 'slug']);
            $table->index('business_id');
        });

        // Widen business_members.role from enum to varchar so custom role slugs are accepted.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE business_members MODIFY COLUMN role VARCHAR(60) NOT NULL DEFAULT 'staff'");
        }
        // SQLite stores enums as TEXT already, so no action needed there.
    }

    public function down(): void
    {
        Schema::dropIfExists('business_roles');

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE business_members MODIFY COLUMN role ENUM('admin','manager','staff') NOT NULL DEFAULT 'staff'");
        }
    }
};
