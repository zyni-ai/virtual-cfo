<?php

use App\Exports\TransactionCsvExport;
use App\Exports\TransactionExcelExport;
use App\Models\AccountHead;
use App\Models\Transaction;
use Maatwebsite\Excel\Facades\Excel;

describe('TransactionCsvExport', function () {
    beforeEach(function () {
        asUser();
    });

    it('produces file with correct headings', function () {
        $head = AccountHead::factory()->create();
        Transaction::factory()->mapped($head)->debit(1000)->create();

        $export = new TransactionCsvExport;

        expect($export->headings())->toBe([
            'Date',
            'Description',
            'Reference',
            'Debit',
            'Credit',
            'Balance',
            'Account Head',
            'Account Head Group',
            'Bank/Source',
            'Mapping Type',
        ]);
    });

    it('maps transactions with decrypted amounts', function () {
        $head = AccountHead::factory()->create([
            'name' => 'Office Rent',
            'group_name' => 'Indirect Expenses',
        ]);
        $transaction = Transaction::factory()->mapped($head)->debit(5000.50)->create([
            'date' => '2025-03-15',
            'description' => 'NEFT-RENT-PAYMENT',
            'reference_number' => 'REF123',
            'balance' => 45000.00,
        ]);
        $transaction->load(['accountHead', 'importedFile']);

        $export = new TransactionCsvExport;
        $row = $export->map($transaction);

        expect($row[0])->toBe('15 Mar 2025')
            ->and($row[1])->toBe('NEFT-RENT-PAYMENT')
            ->and($row[2])->toBe('REF123')
            ->and((float) $row[3])->toBe(5000.50)
            ->and($row[4])->toBeNull()
            ->and((float) $row[5])->toBe(45000.00)
            ->and($row[6])->toBe('Office Rent')
            ->and($row[7])->toBe('Indirect Expenses');
    });

    it('respects date range filter', function () {
        $head = AccountHead::factory()->create();
        $inRange = Transaction::factory()->mapped($head)->create(['date' => '2025-03-15']);
        $outOfRange = Transaction::factory()->mapped($head)->create(['date' => '2025-01-01']);

        $export = new TransactionCsvExport(from: '2025-03-01', until: '2025-03-31');
        $results = $export->query()->get();

        expect($results->pluck('id')->toArray())->toContain($inRange->id)
            ->and($results->pluck('id')->toArray())->not->toContain($outOfRange->id);
    });

    it('only includes mapped transactions by default', function () {
        $head = AccountHead::factory()->create();
        $mapped = Transaction::factory()->mapped($head)->create();
        $unmapped = Transaction::factory()->unmapped()->create();

        $export = new TransactionCsvExport;
        $results = $export->query()->get();

        expect($results->pluck('id')->toArray())->toContain($mapped->id)
            ->and($results->pluck('id')->toArray())->not->toContain($unmapped->id);
    });

    it('is tenant-scoped', function () {
        $currentTenant = tenant();
        $head = AccountHead::factory()->create();
        $ownTransaction = Transaction::factory()->mapped($head)->create();

        // Insert a transaction for a different company directly via DB
        $otherCompany = \App\Models\Company::factory()->create();
        $otherHead = AccountHead::factory()->create();
        \Illuminate\Support\Facades\DB::table('transactions')->insert([
            'company_id' => $otherCompany->id,
            'imported_file_id' => $ownTransaction->imported_file_id,
            'date' => now(),
            'description' => encrypt('Other company transaction'),
            'debit' => encrypt('1000'),
            'account_head_id' => $otherHead->id,
            'mapping_type' => \App\Enums\MappingType::Manual->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherTransactionId = (int) \Illuminate\Support\Facades\DB::getPdo()->lastInsertId();

        $export = new TransactionCsvExport;
        $results = $export->query()->get();

        expect($results->pluck('id')->toArray())->toContain($ownTransaction->id)
            ->and($results->pluck('id')->toArray())->not->toContain($otherTransactionId);
    });

    it('can be downloaded as CSV', function () {
        Excel::fake();

        $head = AccountHead::factory()->create();
        Transaction::factory()->mapped($head)->create();

        $export = new TransactionCsvExport;
        Excel::download($export, 'transactions.csv');

        Excel::assertDownloaded('transactions.csv');
    });
});

describe('TransactionExcelExport', function () {
    beforeEach(function () {
        asUser();
    });

    it('has Transactions and Summary sheets', function () {
        $head = AccountHead::factory()->create();
        Transaction::factory()->mapped($head)->create();

        $export = new TransactionExcelExport;
        $sheets = $export->sheets();

        expect($sheets)->toHaveCount(2)
            ->and($sheets[0]->title())->toBe('Transactions')
            ->and($sheets[1]->title())->toBe('Summary');
    });

    it('summary sheet groups by account head with correct totals', function () {
        $head1 = AccountHead::factory()->create([
            'name' => 'Office Rent',
            'group_name' => 'Indirect Expenses',
        ]);
        $head2 = AccountHead::factory()->create([
            'name' => 'Sales Income',
            'group_name' => 'Direct Income',
        ]);

        Transaction::factory()->mapped($head1)->debit(1000)->create();
        Transaction::factory()->mapped($head1)->debit(2000)->create();
        Transaction::factory()->mapped($head2)->credit(5000)->create();

        $export = new TransactionExcelExport;
        $sheets = $export->sheets();
        $summarySheet = $sheets[1];

        $data = $summarySheet->collection();

        // Should have 2 rows (one per head)
        expect($data)->toHaveCount(2);

        $rentRow = $data->firstWhere('account_head', 'Office Rent');
        $salesRow = $data->firstWhere('account_head', 'Sales Income');

        expect($rentRow)->not->toBeNull()
            ->and((float) $rentRow['total_debit'])->toBe(3000.0)
            ->and((float) $rentRow['total_credit'])->toBe(0.0)
            ->and((float) $rentRow['net_amount'])->toBe(-3000.0)
            ->and($salesRow)->not->toBeNull()
            ->and((float) $salesRow['total_debit'])->toBe(0.0)
            ->and((float) $salesRow['total_credit'])->toBe(5000.0)
            ->and((float) $salesRow['net_amount'])->toBe(5000.0);
    });

    it('can be downloaded as Excel', function () {
        Excel::fake();

        $head = AccountHead::factory()->create();
        Transaction::factory()->mapped($head)->create();

        $export = new TransactionExcelExport;
        Excel::download($export, 'transactions.xlsx');

        Excel::assertDownloaded('transactions.xlsx');
    });
});
