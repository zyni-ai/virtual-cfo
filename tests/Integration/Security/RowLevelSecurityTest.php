<?php

use App\Models\AccountHead;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\HeadMapping;
use App\Models\ImportedFile;
use App\Models\Transaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * RLS tests run in the Integration suite (no LazilyRefreshDatabase)
 * to avoid transaction wrapping that conflicts with SET ROLE.
 * Data is created via factories (committed) and cleaned up manually.
 */
describe('Row-Level Security', function () {
    beforeEach(function () {
        DB::statement("DO $$ BEGIN
            IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'rls_test_user') THEN
                CREATE ROLE rls_test_user NOLOGIN;
            END IF;
        END $$");
        DB::statement('GRANT USAGE ON SCHEMA public TO rls_test_user');
        DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO rls_test_user');
        DB::statement('GRANT USAGE ON ALL SEQUENCES IN SCHEMA public TO rls_test_user');

        $this->companyA = Company::factory()->create();
        $this->companyB = Company::factory()->create();
    });

    afterEach(function () {
        try {
            DB::unprepared('RESET ROLE');
        } catch (Throwable) {
        }

        try {
            DB::unprepared("SET app.current_company_id = ''");
        } catch (Throwable) {
        }

        Transaction::withoutGlobalScopes()->whereIn('company_id', [$this->companyA->id, $this->companyB->id])->forceDelete();
        HeadMapping::withoutGlobalScopes()->whereIn('company_id', [$this->companyA->id, $this->companyB->id])->forceDelete();
        AccountHead::withoutGlobalScopes()->whereIn('company_id', [$this->companyA->id, $this->companyB->id])->forceDelete();
        BankAccount::withoutGlobalScopes()->whereIn('company_id', [$this->companyA->id, $this->companyB->id])->forceDelete();
        ImportedFile::withoutGlobalScopes()->whereIn('company_id', [$this->companyA->id, $this->companyB->id])->forceDelete();
        $this->companyA->forceDelete();
        $this->companyB->forceDelete();
    });

    it('shows all rows when no tenant context is set', function () {
        $fileA = ImportedFile::factory()->for($this->companyA)->create();
        $fileB = ImportedFile::factory()->for($this->companyB)->create();
        Transaction::factory()->for($fileA)->create(['company_id' => $this->companyA->id]);
        Transaction::factory()->for($fileB)->create(['company_id' => $this->companyB->id]);

        DB::unprepared('SET ROLE rls_test_user');
        $count = DB::table('transactions')
            ->whereIn('company_id', [$this->companyA->id, $this->companyB->id])
            ->count();

        expect($count)->toBe(2);
    });

    it('filters transactions by company when tenant context is set', function () {
        $fileA = ImportedFile::factory()->for($this->companyA)->create();
        $fileB = ImportedFile::factory()->for($this->companyB)->create();
        Transaction::factory()->for($fileA)->create(['company_id' => $this->companyA->id]);
        Transaction::factory()->for($fileB)->create(['company_id' => $this->companyB->id]);

        DB::unprepared("SET app.current_company_id = '{$this->companyA->id}'");
        DB::unprepared('SET ROLE rls_test_user');

        $count = DB::table('transactions')->count();

        expect($count)->toBe(1);
    });

    it('company A cannot see company B transactions', function () {
        $fileA = ImportedFile::factory()->for($this->companyA)->create();
        $fileB = ImportedFile::factory()->for($this->companyB)->create();

        $txA = Transaction::factory()->for($fileA)->create(['company_id' => $this->companyA->id]);
        $txB = Transaction::factory()->for($fileB)->create(['company_id' => $this->companyB->id]);

        DB::unprepared("SET app.current_company_id = '{$this->companyA->id}'");
        DB::unprepared('SET ROLE rls_test_user');

        $visibleIds = DB::table('transactions')->pluck('id')->toArray();

        expect($visibleIds)->toContain($txA->id)
            ->and($visibleIds)->not->toContain($txB->id);
    });

    it('enforces RLS on imported_files table', function () {
        ImportedFile::factory()->for($this->companyA)->create();
        ImportedFile::factory()->for($this->companyB)->create();

        DB::unprepared("SET app.current_company_id = '{$this->companyA->id}'");
        DB::unprepared('SET ROLE rls_test_user');

        expect(DB::table('imported_files')->count())->toBe(1);
    });

    it('enforces RLS on account_heads table', function () {
        AccountHead::factory()->for($this->companyA)->create();
        AccountHead::factory()->for($this->companyB)->create();

        DB::unprepared("SET app.current_company_id = '{$this->companyA->id}'");
        DB::unprepared('SET ROLE rls_test_user');

        expect(DB::table('account_heads')->count())->toBe(1);
    });

    it('enforces RLS on head_mappings table', function () {
        HeadMapping::factory()->for($this->companyA)->create();
        HeadMapping::factory()->for($this->companyB)->create();

        DB::unprepared("SET app.current_company_id = '{$this->companyA->id}'");
        DB::unprepared('SET ROLE rls_test_user');

        expect(DB::table('head_mappings')->count())->toBe(1);
    });

    it('enforces RLS on bank_accounts table', function () {
        BankAccount::factory()->for($this->companyA)->create();
        BankAccount::factory()->for($this->companyB)->create();

        DB::unprepared("SET app.current_company_id = '{$this->companyA->id}'");
        DB::unprepared('SET ROLE rls_test_user');

        expect(DB::table('bank_accounts')->count())->toBe(1);
    });

    it('blocks INSERT into wrong tenant via WITH CHECK', function () {
        DB::unprepared("SET app.current_company_id = '{$this->companyA->id}'");
        DB::unprepared('SET ROLE rls_test_user');
        DB::beginTransaction();

        $threw = false;

        try {
            DB::table('account_heads')->insert([
                'name' => 'Salary',
                'company_id' => $this->companyB->id,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $e) {
            $threw = true;
        }

        DB::rollBack();

        expect($threw)->toBeTrue();
    });
});
