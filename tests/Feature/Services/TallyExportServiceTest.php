<?php

use App\Models\AccountHead;
use App\Models\Company;
use App\Models\ImportedFile;
use App\Models\Transaction;
use App\Services\TallyExport\TallyExportService;

describe('TallyExportService::exportForFile()', function () {
    it('generates XML with mapped transactions only', function () {
        $head = AccountHead::factory()->create(['name' => 'Salary Account']);
        $file = ImportedFile::factory()->create();

        Transaction::factory()->mapped($head)->debit(5000)->for($file)->create([
            'description' => 'SALARY JUNE',
            'date' => '2024-06-15',
        ]);
        Transaction::factory()->unmapped()->for($file)->create();

        $service = new TallyExportService;
        $xml = $service->exportForFile($file);

        expect($xml)->toContain('<ENVELOPE>')
            ->and($xml)->toContain('<REPORTNAME>Vouchers</REPORTNAME>')
            ->and($xml)->toContain('Salary Account')
            ->and($xml)->toContain('20240615')
            ->and($xml)->toContain('VCHTYPE="Payment"');
    });

    it('generates receipt voucher for credits', function () {
        $head = AccountHead::factory()->create(['name' => 'Income']);
        $file = ImportedFile::factory()->create();

        Transaction::factory()->mapped($head)->credit(10000)->for($file)->create([
            'description' => 'Client Payment',
            'date' => '2024-07-01',
        ]);

        $service = new TallyExportService;
        $xml = $service->exportForFile($file);

        expect($xml)->toContain('VCHTYPE="Receipt"');
    });

    it('returns empty XML structure when no mapped transactions', function () {
        $file = ImportedFile::factory()->create();
        Transaction::factory()->unmapped()->for($file)->count(3)->create();

        $service = new TallyExportService;
        $xml = $service->exportForFile($file);

        expect($xml)->toContain('<ENVELOPE>')
            ->and($xml)->toContain('<REQUESTDATA>')
            ->and($xml)->not->toContain('<TALLYMESSAGE');
    });
});

describe('TallyExportService::exportTransactions()', function () {
    it('exports a collection of transactions', function () {
        $head = AccountHead::factory()->create(['name' => 'Rent']);
        $transactions = Transaction::factory()->mapped($head)->debit(15000)->count(2)->create([
            'date' => '2024-08-01',
        ]);

        $service = new TallyExportService;
        $xml = $service->exportTransactions($transactions);

        expect($xml)->toContain('<ENVELOPE>')
            ->and($xml)->toContain('Rent')
            ->and(substr_count($xml, '<TALLYMESSAGE'))->toBe(2);
    });
});

describe('TallyExportService with Company', function () {
    it('includes company name in XML header', function () {
        $company = \App\Models\Company::factory()->create(['name' => 'Zysk Technologies']);
        $head = AccountHead::factory()->create(['company_id' => $company->id]);
        $file = ImportedFile::factory()->create(['company_id' => $company->id]);

        Transaction::factory()->mapped($head)->debit(5000)->for($file)->create([
            'company_id' => $company->id,
            'date' => '2024-06-15',
        ]);

        $service = new TallyExportService;
        $xml = $service->exportForFile($file);

        expect($xml)->toContain('Zysk Technologies');
    });
});
