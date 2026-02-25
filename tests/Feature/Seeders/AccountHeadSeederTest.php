<?php

use App\Models\AccountHead;
use Database\Seeders\AccountHeadSeeder;

describe('AccountHeadSeeder', function () {
    it('creates the expected number of account heads', function () {
        $this->seed(AccountHeadSeeder::class);

        // 13 primary groups + 9 sub-groups + 4 bank leaf heads + 1 Cash
        // + 2 Direct Expenses leaves + 15 Indirect Expenses leaves
        // + 2 Direct Incomes leaves + 4 Indirect Incomes leaves
        // + 9 Duties & Taxes leaves = 59 total
        expect(AccountHead::count())->toBe(59);
    });

    it('is idempotent — running twice does not create duplicates', function () {
        $this->seed(AccountHeadSeeder::class);
        $countAfterFirst = AccountHead::count();

        $this->seed(AccountHeadSeeder::class);
        $countAfterSecond = AccountHead::count();

        expect($countAfterSecond)->toBe($countAfterFirst);
    });

    it('creates all 13 primary groups with no parent', function () {
        $this->seed(AccountHeadSeeder::class);

        $primaryGroups = AccountHead::whereNull('parent_id')->pluck('name')->sort()->values();

        expect($primaryGroups->toArray())->toBe([
            'Branch / Divisions',
            'Capital Account',
            'Current Assets',
            'Current Liabilities',
            'Direct Expenses',
            'Direct Incomes',
            'Fixed Assets',
            'Indirect Expenses',
            'Indirect Incomes',
            'Investments',
            'Loans (Liability)',
            'Miscellaneous Expenses (Asset)',
            'Suspense A/c',
        ]);
    });

    it('sets group_name on primary groups to their own name', function () {
        $this->seed(AccountHeadSeeder::class);

        $primaryGroups = AccountHead::whereNull('parent_id')->get();

        $primaryGroups->each(function (AccountHead $group) {
            expect($group->group_name)->toBe($group->name);
        });
    });

    it('creates sub-groups under Current Assets', function () {
        $this->seed(AccountHeadSeeder::class);

        $currentAssets = AccountHead::where('name', 'Current Assets')->whereNull('parent_id')->first();
        $subGroups = $currentAssets->children()->pluck('name')->sort()->values();

        expect($subGroups->toArray())->toBe([
            'Bank Accounts',
            'Cash-in-Hand',
            'Deposits (Asset)',
            'Loans & Advances (Asset)',
            'Stock-in-Hand',
            'Sundry Debtors',
        ]);
    });

    it('creates sub-groups under Current Liabilities', function () {
        $this->seed(AccountHeadSeeder::class);

        $currentLiabilities = AccountHead::where('name', 'Current Liabilities')->whereNull('parent_id')->first();
        $subGroups = $currentLiabilities->children()->pluck('name')->sort()->values();

        expect($subGroups->toArray())->toBe([
            'Duties & Taxes',
            'Provisions',
            'Sundry Creditors',
        ]);
    });

    it('creates bank leaf heads under Bank Accounts', function () {
        $this->seed(AccountHeadSeeder::class);

        $bankAccounts = AccountHead::where('name', 'Bank Accounts')
            ->where('group_name', 'Current Assets')
            ->first();
        $leafHeads = $bankAccounts->children()->pluck('name')->sort()->values();

        expect($leafHeads->toArray())->toBe([
            'Axis Bank',
            'HDFC Bank',
            'ICICI Bank',
            'SBI',
        ]);
    });

    it('creates leaf heads under Indirect Expenses', function () {
        $this->seed(AccountHeadSeeder::class);

        $indirectExpenses = AccountHead::where('name', 'Indirect Expenses')->whereNull('parent_id')->first();
        $leafHeads = $indirectExpenses->children()->pluck('name')->sort()->values();

        expect($leafHeads->toArray())->toBe([
            'Audit Fees',
            'Bank Charges',
            'Conveyance',
            'Depreciation',
            'Electricity',
            'Insurance',
            'Internet',
            'Miscellaneous Expenses',
            'Printing & Stationery',
            'Professional Fees',
            'Rent',
            'Repairs & Maintenance',
            'Salary',
            'Telephone',
            'Travelling',
        ]);
    });

    it('creates tax-related leaf heads under Duties & Taxes', function () {
        $this->seed(AccountHeadSeeder::class);

        $dutiesAndTaxes = AccountHead::where('name', 'Duties & Taxes')
            ->where('group_name', 'Current Liabilities')
            ->first();
        $leafHeads = $dutiesAndTaxes->children()->pluck('name')->sort()->values();

        expect($leafHeads->toArray())->toBe([
            'GST Input CGST',
            'GST Input IGST',
            'GST Input SGST',
            'GST Output CGST',
            'GST Output IGST',
            'GST Output SGST',
            'Professional Tax',
            'TDS Payable',
            'TDS Receivable',
        ]);
    });

    it('sets group_name on sub-groups and leaf heads to their top-level group', function () {
        $this->seed(AccountHeadSeeder::class);

        // Sub-group should have parent's group_name
        $bankAccounts = AccountHead::where('name', 'Bank Accounts')
            ->where('group_name', 'Current Assets')
            ->first();
        expect($bankAccounts->group_name)->toBe('Current Assets');

        // Leaf under sub-group should have top-level group_name
        $hdfcBank = AccountHead::where('name', 'HDFC Bank')->first();
        expect($hdfcBank->group_name)->toBe('Current Assets');

        // Leaf directly under primary group
        $salary = AccountHead::where('name', 'Salary')->first();
        expect($salary->group_name)->toBe('Indirect Expenses');
    });

    it('marks all seeded heads as active', function () {
        $this->seed(AccountHeadSeeder::class);

        $inactiveCount = AccountHead::where('is_active', false)->count();

        expect($inactiveCount)->toBe(0);
    });
});
