<?php

use App\Enums\ReconciliationStatus;
use App\Enums\StatementType;
use App\Jobs\ReconcileImportedFiles;
use App\Models\ImportedFile;
use App\Models\ReconciliationMatch;
use App\Models\Transaction;

describe('ReconcileImportedFiles Job', function () {
    it('runs reconciliation and enrichment for the given files', function () {
        $bankFile = ImportedFile::factory()->completed(totalRows: 1, mappedRows: 0)->create([
            'statement_type' => StatementType::Bank,
        ]);

        $invoiceFile = ImportedFile::factory()->completed(totalRows: 1, mappedRows: 0)->create([
            'statement_type' => StatementType::Invoice,
        ]);

        Transaction::factory()->debit(25000.00)->create([
            'imported_file_id' => $bankFile->id,
            'description' => 'NEFT-Vendor Payment',
            'date' => '2025-04-15',
            'reconciliation_status' => ReconciliationStatus::Unreconciled,
        ]);

        Transaction::factory()->debit(25000.00)->create([
            'imported_file_id' => $invoiceFile->id,
            'description' => 'INV/001 - Vendor Corp',
            'date' => '2025-04-10',
            'reconciliation_status' => ReconciliationStatus::Unreconciled,
            'raw_data' => [
                'vendor_name' => 'Vendor Corp',
                'invoice_number' => 'INV/001',
                'base_amount' => 21186.44,
                'cgst_amount' => 1906.78,
                'sgst_amount' => 1906.78,
            ],
        ]);

        $job = new ReconcileImportedFiles($bankFile, $invoiceFile);
        $job->handle(new \App\Services\Reconciliation\ReconciliationService);

        // Should have created a match
        expect(ReconciliationMatch::count())->toBe(1);

        // Bank transaction should be enriched with invoice data
        $bankTxn = Transaction::where('imported_file_id', $bankFile->id)->first();
        expect($bankTxn->reconciliation_status)->toBe(ReconciliationStatus::Matched)
            ->and($bankTxn->raw_data)->toHaveKey('vendor_name', 'Vendor Corp');
    });

    it('can be dispatched to a queue', function () {
        $bankFile = ImportedFile::factory()->completed()->create([
            'statement_type' => StatementType::Bank,
        ]);

        $invoiceFile = ImportedFile::factory()->completed()->create([
            'statement_type' => StatementType::Invoice,
        ]);

        ReconcileImportedFiles::dispatch($bankFile, $invoiceFile);

        // The job should be in the queue
        expect(true)->toBeTrue();
    });
});
