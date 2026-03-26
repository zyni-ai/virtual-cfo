<?php

use App\Exports\TransactionExcelExport;
use App\Exports\TransactionSummarySheet;
use App\Models\AccountHead;
use App\Models\CreditCard;
use App\Models\ImportedFile;
use App\Models\Transaction;

describe('TransactionSummarySheet metadata', function () {
    beforeEach(function () {
        asUser();
    });

    it('includes importedFile context when constructed with one', function () {
        $file = ImportedFile::factory()->create([
            'bank_name' => 'ICICI',
            'statement_period' => 'Feb 2026',
        ]);

        $sheet = new TransactionSummarySheet(importedFile: $file);

        expect($sheet->importedFile)->toBe($file);
    });

    it('omits metadata when no importedFile context', function () {
        $sheet = new TransactionSummarySheet;

        expect($sheet->importedFile)->toBeNull();
    });

    it('collection data is still correct when importedFile is provided', function () {
        $head = AccountHead::factory()->create(['name' => 'Office Rent']);
        $file = ImportedFile::factory()->creditCard()->create([
            'bank_name' => 'ICICI',
            'statement_period' => 'Feb 2026',
        ]);

        Transaction::factory()->mapped($head)->debit(3000)->create([
            'imported_file_id' => $file->id,
        ]);

        $baseQuery = Transaction::query()->where('imported_file_id', $file->id);
        $sheet = new TransactionSummarySheet(baseQuery: $baseQuery, importedFile: $file);
        $data = $sheet->collection();

        expect($data)->toHaveCount(1)
            ->and($data->first()['account_head'])->toBe('Office Rent');
    });
});

describe('TransactionExcelExport with importedFile context', function () {
    beforeEach(function () {
        asUser();
    });

    it('passes importedFile to the summary sheet', function () {
        $file = ImportedFile::factory()->creditCard()->create([
            'bank_name' => 'ICICI',
            'statement_period' => 'Feb 2026',
        ]);

        $baseQuery = Transaction::query()->where('imported_file_id', $file->id);
        $export = new TransactionExcelExport(baseQuery: $baseQuery, importedFile: $file);
        $sheets = $export->sheets();

        expect($sheets[1]->importedFile)->toBe($file);
    });

    it('summary sheet has no importedFile when not provided', function () {
        $export = new TransactionExcelExport;
        $sheets = $export->sheets();

        expect($sheets[1]->importedFile)->toBeNull();
    });

    it('displays card name with credit card context', function () {
        $card = CreditCard::factory()->create([
            'name' => 'Platinum',
            'company_id' => tenant()->id,
        ]);

        $file = ImportedFile::factory()->creditCard()->create([
            'bank_name' => 'ICICI',
            'credit_card_id' => $card->id,
            'statement_period' => 'Feb 2026 – Mar 2026',
        ]);

        $sheet = new TransactionSummarySheet(importedFile: $file);

        expect($sheet->resolveAccountLabel())->toBe('ICICI Platinum');
    });

    it('displays bank name for bank account imports', function () {
        $file = ImportedFile::factory()->create([
            'bank_name' => 'HDFC Bank',
            'statement_period' => 'Jan 2026',
        ]);

        $sheet = new TransactionSummarySheet(importedFile: $file);

        expect($sheet->resolveAccountLabel())->toBe('HDFC Bank');
    });
});
