<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * SQLite created a CHECK constraint for the original enum('admin','manager','staff')
     * on business_members.role. The previous migration widened it for MySQL but left
     * SQLite unchanged. Recreate the table without the role CHECK constraint so that
     * custom role slugs (e.g. 'reporter') are accepted on all drivers.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return; // MySQL was already fixed in 2026_07_08_000001
        }

        DB::statement('PRAGMA foreign_keys = OFF');

        DB::statement("
            CREATE TABLE business_members_new (
                id         INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                business_id INTEGER NOT NULL,
                user_id     INTEGER NOT NULL,
                role        VARCHAR(60) NOT NULL DEFAULT 'staff',
                permissions TEXT    NULL,
                status      VARCHAR(255) NOT NULL DEFAULT 'active'
                            CHECK (status IN ('active','pending')),
                invited_by  INTEGER NULL,
                created_at  DATETIME NULL,
                updated_at  DATETIME NULL,
                FOREIGN KEY (business_id) REFERENCES businesses(id)  ON DELETE CASCADE,
                FOREIGN KEY (user_id)     REFERENCES users(id)       ON DELETE CASCADE,
                FOREIGN KEY (invited_by)  REFERENCES users(id)       ON DELETE SET NULL,
                UNIQUE (business_id, user_id)
            )
        ");

        DB::statement('INSERT INTO business_members_new SELECT * FROM business_members');
        DB::statement('DROP TABLE business_members');
        DB::statement('ALTER TABLE business_members_new RENAME TO business_members');

        DB::statement('PRAGMA foreign_keys = ON');
    }

    public function down(): void
    {
        // Not reversible in a meaningful way; the enum constraint was a mistake.
    }
};
