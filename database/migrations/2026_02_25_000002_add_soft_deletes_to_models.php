<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_heads', function (Blueprint $table) {
            $table->softDeletesTz();
        });

        Schema::table('imported_files', function (Blueprint $table) {
            $table->softDeletesTz();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->softDeletesTz();
        });

        // Convert the existing unique constraint on account_heads to a partial
        // unique index that only applies to non-deleted records (PostgreSQL rule:
        // partial unique with WHERE deleted_at IS NULL when using soft deletes).
        // Must drop the constraint (not index) because Laravel's unique() creates
        // a constraint in PostgreSQL.
        Schema::table('account_heads', function (Blueprint $table) {
            $table->dropUnique('account_heads_name_group_unique');
        });
        DB::statement(
            'CREATE UNIQUE INDEX account_heads_name_group_unique ON account_heads (name, group_name) WHERE deleted_at IS NULL'
        );

        // Convert the existing unique constraint on imported_files.file_hash to
        // a partial unique index as well.
        // Drop the Laravel-generated unique index first.
        Schema::table('imported_files', function (Blueprint $table) {
            $table->dropUnique(['file_hash']);
        });
        DB::statement(
            'CREATE UNIQUE INDEX imported_files_file_hash_unique ON imported_files (file_hash) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        // Restore original unique constraints
        DB::statement('DROP INDEX IF EXISTS imported_files_file_hash_unique');
        Schema::table('imported_files', function (Blueprint $table) {
            $table->unique('file_hash');
        });

        DB::statement('DROP INDEX IF EXISTS account_heads_name_group_unique');
        Schema::table('account_heads', function (Blueprint $table) {
            $table->unique(['name', 'group_name'], 'account_heads_name_group_unique');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropSoftDeletesTz();
        });

        Schema::table('imported_files', function (Blueprint $table) {
            $table->dropSoftDeletesTz();
        });

        Schema::table('account_heads', function (Blueprint $table) {
            $table->dropSoftDeletesTz();
        });
    }
};
