<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'CREATE UNIQUE INDEX head_mappings_unique_rule ON head_mappings (company_id, pattern, match_type, account_head_id)'
        );
    }
};
