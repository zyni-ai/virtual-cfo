<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Add composite indexes matching tenant-scoped query patterns
            $table->index(['company_id', 'date'], 'transactions_company_date_idx');
            $table->index(['company_id', 'mapping_type'], 'transactions_company_mapping_idx');
            $table->index(['company_id', 'reconciliation_status'], 'transactions_company_recon_idx');
            $table->index(['company_id', 'account_head_id'], 'transactions_company_head_idx');

            // Drop single-column indexes now superseded by composites above
            $table->dropIndex('transactions_date_index');
            $table->dropIndex('transactions_mapping_type_index');
            $table->dropIndex('transactions_reconciliation_status_index');
            $table->dropIndex('transactions_account_head_id_index');
        });

        Schema::table('imported_files', function (Blueprint $table) {
            // Tenant + status for pending/failed/needs_password file lists
            $table->index(['company_id', 'status'], 'imported_files_company_status_idx');

            // Drop single-column status index now superseded
            $table->dropIndex('imported_files_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('imported_files', function (Blueprint $table) {
            $table->index('status');
            $table->dropIndex('imported_files_company_status_idx');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->index('date');
            $table->index('mapping_type');
            $table->index('reconciliation_status');
            $table->index('account_head_id');

            $table->dropIndex('transactions_company_date_idx');
            $table->dropIndex('transactions_company_mapping_idx');
            $table->dropIndex('transactions_company_recon_idx');
            $table->dropIndex('transactions_company_head_idx');
        });
    }
};
