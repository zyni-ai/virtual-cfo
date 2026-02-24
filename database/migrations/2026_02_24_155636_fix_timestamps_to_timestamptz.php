<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convert all timestamp columns from TIMESTAMP to TIMESTAMPTZ.
     *
     * Financial data requires timezone-aware timestamps for correct
     * date/time handling in India (Asia/Kolkata).
     */
    public function up(): void
    {
        // users
        DB::statement("ALTER TABLE users ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC'");
        DB::statement("ALTER TABLE users ALTER COLUMN updated_at TYPE TIMESTAMPTZ USING updated_at AT TIME ZONE 'UTC'");
        DB::statement("ALTER TABLE users ALTER COLUMN email_verified_at TYPE TIMESTAMPTZ USING email_verified_at AT TIME ZONE 'UTC'");

        // password_reset_tokens
        DB::statement("ALTER TABLE password_reset_tokens ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC'");

        // failed_jobs
        DB::statement("ALTER TABLE failed_jobs ALTER COLUMN failed_at TYPE TIMESTAMPTZ USING failed_at AT TIME ZONE 'UTC'");

        // account_heads
        DB::statement("ALTER TABLE account_heads ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC'");
        DB::statement("ALTER TABLE account_heads ALTER COLUMN updated_at TYPE TIMESTAMPTZ USING updated_at AT TIME ZONE 'UTC'");

        // imported_files
        DB::statement("ALTER TABLE imported_files ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC'");
        DB::statement("ALTER TABLE imported_files ALTER COLUMN updated_at TYPE TIMESTAMPTZ USING updated_at AT TIME ZONE 'UTC'");
        DB::statement("ALTER TABLE imported_files ALTER COLUMN processed_at TYPE TIMESTAMPTZ USING processed_at AT TIME ZONE 'UTC'");

        // transactions
        DB::statement("ALTER TABLE transactions ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC'");
        DB::statement("ALTER TABLE transactions ALTER COLUMN updated_at TYPE TIMESTAMPTZ USING updated_at AT TIME ZONE 'UTC'");

        // head_mappings
        DB::statement("ALTER TABLE head_mappings ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC'");
        DB::statement("ALTER TABLE head_mappings ALTER COLUMN updated_at TYPE TIMESTAMPTZ USING updated_at AT TIME ZONE 'UTC'");

    }

    /**
     * Reverse the conversion back to TIMESTAMP.
     */
    public function down(): void
    {
        // users
        DB::statement('ALTER TABLE users ALTER COLUMN created_at TYPE TIMESTAMP');
        DB::statement('ALTER TABLE users ALTER COLUMN updated_at TYPE TIMESTAMP');
        DB::statement('ALTER TABLE users ALTER COLUMN email_verified_at TYPE TIMESTAMP');

        // password_reset_tokens
        DB::statement('ALTER TABLE password_reset_tokens ALTER COLUMN created_at TYPE TIMESTAMP');

        // failed_jobs
        DB::statement('ALTER TABLE failed_jobs ALTER COLUMN failed_at TYPE TIMESTAMP');

        // account_heads
        DB::statement('ALTER TABLE account_heads ALTER COLUMN created_at TYPE TIMESTAMP');
        DB::statement('ALTER TABLE account_heads ALTER COLUMN updated_at TYPE TIMESTAMP');

        // imported_files
        DB::statement('ALTER TABLE imported_files ALTER COLUMN created_at TYPE TIMESTAMP');
        DB::statement('ALTER TABLE imported_files ALTER COLUMN updated_at TYPE TIMESTAMP');
        DB::statement('ALTER TABLE imported_files ALTER COLUMN processed_at TYPE TIMESTAMP');

        // transactions
        DB::statement('ALTER TABLE transactions ALTER COLUMN created_at TYPE TIMESTAMP');
        DB::statement('ALTER TABLE transactions ALTER COLUMN updated_at TYPE TIMESTAMP');

        // head_mappings
        DB::statement('ALTER TABLE head_mappings ALTER COLUMN created_at TYPE TIMESTAMP');
        DB::statement('ALTER TABLE head_mappings ALTER COLUMN updated_at TYPE TIMESTAMP');

    }
};
