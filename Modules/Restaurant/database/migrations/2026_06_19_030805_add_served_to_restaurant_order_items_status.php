<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE restaurant_order_items MODIFY COLUMN status ENUM('pending','preparing','ready','served') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("UPDATE restaurant_order_items SET status = 'ready' WHERE status = 'served'");
        DB::statement("ALTER TABLE restaurant_order_items MODIFY COLUMN status ENUM('pending','preparing','ready') NOT NULL DEFAULT 'pending'");
    }
};
