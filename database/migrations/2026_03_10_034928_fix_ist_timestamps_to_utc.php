<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fix IST timestamps stored as UTC.
 *
 * The app was configured with timezone = Asia/Kolkata, so Carbon::now()
 * generated IST timestamps. PostgreSQL stored them as +00 (UTC) because
 * the DB session timezone was UTC. This migration shifts all existing
 * TIMESTAMPTZ values back by 5:30 hours to correct them.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> */
    private array $tables = [
        'account_heads' => ['created_at', 'updated_at', 'deleted_at'],
        'activity_log' => ['created_at', 'updated_at'],
        'bank_accounts' => ['created_at', 'updated_at', 'deleted_at'],
        'budgets' => ['created_at', 'updated_at'],
        'companies' => ['created_at', 'updated_at'],
        'company_credit_card' => ['created_at', 'updated_at'],
        'company_user' => ['created_at', 'updated_at'],
        'connectors' => ['created_at', 'updated_at', 'deleted_at', 'last_synced_at', 'token_expires_at'],
        'credit_cards' => ['created_at', 'updated_at', 'deleted_at'],
        'duplicate_flags' => ['created_at', 'updated_at', 'resolved_at'],
        'failed_jobs' => ['failed_at'],
        'head_mappings' => ['created_at', 'updated_at'],
        'imported_files' => ['created_at', 'updated_at', 'deleted_at', 'processed_at'],
        'invitations' => ['created_at', 'updated_at', 'accepted_at', 'expires_at'],
        'login_sessions' => ['created_at', 'updated_at', 'last_active_at', 'logged_in_at', 'logged_out_at'],
        'notifications' => ['created_at', 'updated_at', 'read_at'],
        'password_reset_tokens' => ['created_at'],
        'reconciliation_matches' => ['created_at', 'updated_at', 'deleted_at'],
        'recurring_patterns' => ['created_at', 'updated_at'],
        'transaction_aggregates' => ['created_at', 'updated_at'],
        'transactions' => ['created_at', 'updated_at', 'deleted_at'],
        'users' => ['created_at', 'updated_at', 'email_verified_at'],
    ];

    public function up(): void
    {
        foreach ($this->tables as $table => $columns) {
            foreach ($columns as $column) {
                DB::statement(
                    "UPDATE {$table} SET {$column} = {$column} - INTERVAL '5 hours 30 minutes' WHERE {$column} IS NOT NULL"
                );
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table => $columns) {
            foreach ($columns as $column) {
                DB::statement(
                    "UPDATE {$table} SET {$column} = {$column} + INTERVAL '5 hours 30 minutes' WHERE {$column} IS NOT NULL"
                );
            }
        }
    }
};
