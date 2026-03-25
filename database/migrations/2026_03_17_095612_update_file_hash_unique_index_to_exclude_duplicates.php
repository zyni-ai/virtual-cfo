<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Extend the company-scoped file_hash unique index to also exclude
     * duplicate-status records, so a duplicate ImportedFile can share
     * the same file_hash as the original without violating the constraint.
     */
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS imported_files_company_file_hash_unique');
        DB::statement("
            CREATE UNIQUE INDEX imported_files_company_file_hash_unique
            ON imported_files (company_id, file_hash)
            WHERE deleted_at IS NULL AND status != 'duplicate'
        ");
    }
};
