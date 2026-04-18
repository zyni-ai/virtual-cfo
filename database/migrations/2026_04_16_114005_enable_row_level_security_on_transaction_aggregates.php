<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE transaction_aggregates ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE transaction_aggregates FORCE ROW LEVEL SECURITY');

        DB::statement("
            CREATE POLICY tenant_isolation_transaction_aggregates ON transaction_aggregates
                USING (
                    CASE
                        WHEN current_setting('app.current_company_id', true) IS NULL
                             OR current_setting('app.current_company_id', true) = ''
                        THEN true
                        ELSE company_id = current_setting('app.current_company_id', true)::bigint
                    END
                )
                WITH CHECK (
                    CASE
                        WHEN current_setting('app.current_company_id', true) IS NULL
                             OR current_setting('app.current_company_id', true) = ''
                        THEN true
                        ELSE company_id = current_setting('app.current_company_id', true)::bigint
                    END
                )
        ");
    }
};
