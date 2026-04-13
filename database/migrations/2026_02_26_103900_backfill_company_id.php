<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Create the initial company from config defaults.
        // NOTE: This migration ran once in production. The config/company.php file does not
        // exist; config() calls resolve to null and the DB defaults ('Regular', 'INR') apply.
        // On fresh installs the company created here will be replaced via RegisterCompany.
        $companyId = DB::table('companies')->insertGetId([
            'name' => config('company.name', ''),
            'gstin' => config('company.gstin'),
            'state' => config('company.state'),
            'gst_registration_type' => config('company.gst_registration_type', 'Regular'),
            'currency' => config('company.currency', 'INR'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Attach all existing users to this company
        $userIds = DB::table('users')->pluck('id');
        foreach ($userIds as $userId) {
            DB::table('company_user')->insert([
                'company_id' => $companyId,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Backfill company_id on all existing rows
        DB::table('imported_files')->whereNull('company_id')->update(['company_id' => $companyId]);
        DB::table('account_heads')->whereNull('company_id')->update(['company_id' => $companyId]);
        DB::table('head_mappings')->whereNull('company_id')->update(['company_id' => $companyId]);
        DB::table('transactions')->whereNull('company_id')->update(['company_id' => $companyId]);

        // Make company_id NOT NULL after backfill
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
