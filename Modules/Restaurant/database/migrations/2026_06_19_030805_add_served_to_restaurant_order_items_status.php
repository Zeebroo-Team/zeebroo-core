<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite cannot ALTER COLUMN; recreate the table with 'served' added to the status enum.
        DB::statement('PRAGMA foreign_keys = OFF');

        DB::statement("
            CREATE TABLE restaurant_order_items_new (
                id          INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                order_id    INTEGER NOT NULL,
                menu_item_id INTEGER,
                name        VARCHAR NOT NULL,
                quantity    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                unit_price  DECIMAL(12,2)     NOT NULL DEFAULT 0,
                notes       TEXT,
                status      VARCHAR NOT NULL DEFAULT 'pending'
                            CHECK(status IN ('pending','preparing','ready','served')),
                created_at  DATETIME,
                updated_at  DATETIME,
                FOREIGN KEY (order_id)     REFERENCES restaurant_orders(id)     ON DELETE CASCADE,
                FOREIGN KEY (menu_item_id) REFERENCES restaurant_menu_items(id) ON DELETE SET NULL
            )
        ");

        DB::statement('INSERT INTO restaurant_order_items_new SELECT * FROM restaurant_order_items');
        DB::statement('DROP TABLE restaurant_order_items');
        DB::statement('ALTER TABLE restaurant_order_items_new RENAME TO restaurant_order_items');

        DB::statement('PRAGMA foreign_keys = ON');
    }

    public function down(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        DB::statement("
            CREATE TABLE restaurant_order_items_new (
                id          INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                order_id    INTEGER NOT NULL,
                menu_item_id INTEGER,
                name        VARCHAR NOT NULL,
                quantity    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                unit_price  DECIMAL(12,2)     NOT NULL DEFAULT 0,
                notes       TEXT,
                status      VARCHAR NOT NULL DEFAULT 'pending'
                            CHECK(status IN ('pending','preparing','ready')),
                created_at  DATETIME,
                updated_at  DATETIME,
                FOREIGN KEY (order_id)     REFERENCES restaurant_orders(id)     ON DELETE CASCADE,
                FOREIGN KEY (menu_item_id) REFERENCES restaurant_menu_items(id) ON DELETE SET NULL
            )
        ");

        DB::statement("
            INSERT INTO restaurant_order_items_new
            SELECT id, order_id, menu_item_id, name, quantity, unit_price, notes,
                   CASE WHEN status = 'served' THEN 'ready' ELSE status END,
                   created_at, updated_at
            FROM restaurant_order_items
        ");
        DB::statement('DROP TABLE restaurant_order_items');
        DB::statement('ALTER TABLE restaurant_order_items_new RENAME TO restaurant_order_items');

        DB::statement('PRAGMA foreign_keys = ON');
    }
};
