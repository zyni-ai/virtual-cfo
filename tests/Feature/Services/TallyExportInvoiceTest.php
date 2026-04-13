<?php

use App\Enums\StatementType;
use App\Models\AccountHead;
use App\Models\ImportedFile;
use App\Models\Transaction;
use App\Services\TallyExport\TallyExportService;

describe('TallyExportService invoice Journal voucher', function () {
    beforeEach(fn () => asUser());

    it('exports invoice transaction as Journal voucher not Payment', function () {
        $file = ImportedFile::factory()->create([
            'statement_type' => StatementType::Invoice,
            'company_id' => tenant()->id,
        ]);
        $head = AccountHead::factory()->create(['company_id' => tenant()->id]);
        Transaction::factory()->mapped($head)->create([
            'imported_file_id' => $file->id,
            'company_id' => tenant()->id,
            'debit' => '31900',
            'date' => '2025-04-01',
            'raw_data' => [
                'vendor_name' => 'Test Vendor Pvt Ltd',
                'vendor_gstin' => '29AAQCA1895C1ZD',
                'invoice_number' => 'INV/001',
                'base_amount' => 27500.00,
                'cgst_rate' => 9,
                'cgst_amount' => 2475.00,
                'sgst_rate' => 9,
                'sgst_amount' => 2475.00,
                'igst_amount' => null,
                'tds_amount' => 0,
                'total_amount' => 31900.00,
            ],
        ]);

        $xml = app(TallyExportService::class)->exportForFile($file);

        expect($xml)->toContain('Journal')->not->toContain('Payment');
    });

    it('includes separate CGST and SGST legs in the journal voucher', function () {
        $file = ImportedFile::factory()->create([
            'statement_type' => StatementType::Invoice,
            'company_id' => tenant()->id,
        ]);
        $head = AccountHead::factory()->create([
            'company_id' => tenant()->id,
            'name' => 'Manpower Supply Charges',
        ]);
        Transaction::factory()->mapped($head)->create([
            'imported_file_id' => $file->id,
            'company_id' => tenant()->id,
            'debit' => '31900',
            'date' => '2025-04-01',
            'raw_data' => [
                'vendor_name' => 'Test Vendor Pvt Ltd',
                'base_amount' => 27500.00,
                'cgst_rate' => 9,
                'cgst_amount' => 2475.00,
                'sgst_rate' => 9,
                'sgst_amount' => 2475.00,
                'igst_amount' => null,
                'tds_amount' => 0,
                'total_amount' => 31900.00,
            ],
        ]);

        $xml = app(TallyExportService::class)->exportForFile($file);

        expect($xml)
            ->toContain('Input Cgst @ 9%')
            ->toContain('Input Sgst @ 9%')
            ->toContain('-2475.00')
            ->toContain('-27500.00');
    });

    it('includes TDS credit leg when tds_amount is non-zero', function () {
        $file = ImportedFile::factory()->create([
            'statement_type' => StatementType::Invoice,
            'company_id' => tenant()->id,
        ]);
        $head = AccountHead::factory()->create(['company_id' => tenant()->id]);
        Transaction::factory()->mapped($head)->create([
            'imported_file_id' => $file->id,
            'company_id' => tenant()->id,
            'debit' => '31350',
            'date' => '2025-04-01',
            'raw_data' => [
                'vendor_name' => 'Assetpro Solution Pvt Ltd',
                'base_amount' => 27500.00,
                'cgst_rate' => 9,
                'cgst_amount' => 2475.00,
                'sgst_rate' => 9,
                'sgst_amount' => 2475.00,
                'igst_amount' => null,
                'tds_amount' => 550.00,
                'total_amount' => 31900.00,
            ],
        ]);

        $xml = app(TallyExportService::class)->exportForFile($file);

        expect($xml)
            ->toContain('TDS Payable')
            ->toContain('550.00');
    });

    it('uses IGST leg instead of CGST/SGST for inter-state invoices', function () {
        $file = ImportedFile::factory()->create([
            'statement_type' => StatementType::Invoice,
            'company_id' => tenant()->id,
        ]);
        $head = AccountHead::factory()->create(['company_id' => tenant()->id]);
        Transaction::factory()->mapped($head)->create([
            'imported_file_id' => $file->id,
            'company_id' => tenant()->id,
            'debit' => '31900',
            'date' => '2025-04-01',
            'raw_data' => [
                'vendor_name' => 'Inter State Vendor Ltd',
                'base_amount' => 27500.00,
                'cgst_rate' => null,
                'cgst_amount' => null,
                'sgst_rate' => null,
                'sgst_amount' => null,
                'igst_rate' => 18,
                'igst_amount' => 4950.00,
                'tds_amount' => 0,
                'total_amount' => 32450.00,
            ],
        ]);

        $xml = app(TallyExportService::class)->exportForFile($file);

        expect($xml)
            ->toContain('Input Igst @ 18%')
            ->toContain('-4950.00')
            ->not->toContain('Input Cgst')
            ->not->toContain('Input Sgst');
    });

    it('includes vendor party ledger as credit leg', function () {
        $file = ImportedFile::factory()->create([
            'statement_type' => StatementType::Invoice,
            'company_id' => tenant()->id,
        ]);
        $head = AccountHead::factory()->create(['company_id' => tenant()->id]);
        Transaction::factory()->mapped($head)->create([
            'imported_file_id' => $file->id,
            'company_id' => tenant()->id,
            'debit' => '31900',
            'date' => '2025-04-01',
            'raw_data' => [
                'vendor_name' => 'Test Vendor Pvt Ltd',
                'vendor_gstin' => '29AAQCA1895C1ZD',
                'base_amount' => 27500.00,
                'cgst_rate' => 9,
                'cgst_amount' => 2475.00,
                'sgst_rate' => 9,
                'sgst_amount' => 2475.00,
                'igst_amount' => null,
                'tds_amount' => 0,
                'total_amount' => 31900.00,
            ],
        ]);

        $xml = app(TallyExportService::class)->exportForFile($file);

        expect($xml)
            ->toContain('<ISPARTYLEDGER>Yes</ISPARTYLEDGER>')
            ->toContain('31900.00')
            ->toContain('29AAQCA1895C1ZD');
    });
});
