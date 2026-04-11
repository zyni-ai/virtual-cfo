<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // This is a one-time backfill for databases that pre-date multi-tenancy.
        // On a fresh install every table is empty — there is nothing to backfill
        // and creating a default company here would pollute test databases.
        $userIds = DB::table('users')->pluck('id');

        if ($userIds->isNotEmpty()) {
            $companyId = DB::table('companies')->insertGetId([
                'name' => config('company.name', 'Zysk Technologies Private Limited - 2025 - 2026'),
                'gstin' => config('company.gstin', '29AABCZ5012F1ZG'),
                'state' => config('company.state', 'Karnataka'),
                'gst_registration_type' => config('company.gst_registration_type', 'Regular'),
                'financial_year' => config('company.financial_year', '2025-2026'),
                'currency' => config('company.currency', 'INR'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($userIds as $userId) {
                DB::table('company_user')->insert([
                    'company_id' => $companyId,
                    'user_id' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('imported_files')->whereNull('company_id')->update(['company_id' => $companyId]);
            DB::table('account_heads')->whereNull('company_id')->update(['company_id' => $companyId]);
            DB::table('head_mappings')->whereNull('company_id')->update(['company_id' => $companyId]);
            DB::table('transactions')->whereNull('company_id')->update(['company_id' => $companyId]);
        }

        // Applied outside the guard — safe on empty tables, required after backfill
        DB::statement('ALTER TABLE imported_files ALTER COLUMN company_id SET NOT NULL');
        DB::statement('ALTER TABLE account_heads ALTER COLUMN company_id SET NOT NULL');
        DB::statement('ALTER TABLE head_mappings ALTER COLUMN company_id SET NOT NULL');
        DB::statement('ALTER TABLE transactions ALTER COLUMN company_id SET NOT NULL');
    }

    public function down(): void
    {
        // Make company_id nullable again
        DB::statement('ALTER TABLE imported_files ALTER COLUMN company_id DROP NOT NULL');
        DB::statement('ALTER TABLE account_heads ALTER COLUMN company_id DROP NOT NULL');
        DB::statement('ALTER TABLE head_mappings ALTER COLUMN company_id DROP NOT NULL');
        DB::statement('ALTER TABLE transactions ALTER COLUMN company_id DROP NOT NULL');
    }
};
