<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'CREATE UNIQUE INDEX account_heads_company_tally_guid_unique ON account_heads (company_id, tally_guid) WHERE deleted_at IS NULL AND tally_guid IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS account_heads_company_tally_guid_unique');
    }
};
