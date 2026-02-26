<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Update partial unique index to include company_id for multi-tenancy
        DB::statement('DROP INDEX IF EXISTS account_heads_name_group_unique');
        DB::statement(
            'CREATE UNIQUE INDEX account_heads_name_group_unique ON account_heads (company_id, name, group_name) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS account_heads_name_group_unique');
        DB::statement(
            'CREATE UNIQUE INDEX account_heads_name_group_unique ON account_heads (name, group_name) WHERE deleted_at IS NULL'
        );
    }
};
