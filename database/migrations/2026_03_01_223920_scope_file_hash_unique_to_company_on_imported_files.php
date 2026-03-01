<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Scope file_hash uniqueness to company_id so different companies
     * can upload the same file independently.
     */
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS imported_files_file_hash_unique');
        DB::statement('
            CREATE UNIQUE INDEX imported_files_company_file_hash_unique
            ON imported_files (company_id, file_hash)
            WHERE deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS imported_files_company_file_hash_unique');
        DB::statement('
            CREATE UNIQUE INDEX imported_files_file_hash_unique
            ON imported_files (file_hash)
        ');
    }
};
