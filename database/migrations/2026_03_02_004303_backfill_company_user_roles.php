<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill pivot roles from global user roles
        DB::statement(<<<'SQL'
            UPDATE company_user
            SET role = users.role
            FROM users
            WHERE company_user.user_id = users.id
              AND company_user.role IS NULL
        SQL);

        // Make role NOT NULL with default 'viewer'
        DB::statement(<<<'SQL'
            ALTER TABLE company_user
            ALTER COLUMN role SET DEFAULT 'viewer',
            ALTER COLUMN role SET NOT NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE company_user
            ALTER COLUMN role DROP NOT NULL,
            ALTER COLUMN role DROP DEFAULT
        SQL);
    }
};
