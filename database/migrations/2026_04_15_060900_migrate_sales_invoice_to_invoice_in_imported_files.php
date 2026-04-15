<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Migrate stale 'sales_invoice' rows to 'invoice'.
     *
     * sales_invoice was removed as a separate StatementType enum case.
     * Sales invoices are now distinguished from purchase invoices by the
     * presence of 'buyer_name' in the transaction raw_data at export time.
     */
    public function up(): void
    {
        DB::table('imported_files')
            ->where('statement_type', 'sales_invoice')
            ->update(['statement_type' => 'invoice']);
    }
};
