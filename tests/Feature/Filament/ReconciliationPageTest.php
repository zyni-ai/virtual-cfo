<?php

use App\Enums\ImportStatus;
use App\Enums\MatchMethod;
use App\Enums\ReconciliationStatus;
use App\Enums\StatementType;
use App\Filament\Pages\Reconciliation;
use App\Jobs\ReconcileImportedFiles;
use App\Models\ImportedFile;
use App\Models\ReconciliationMatch;
use App\Models\Transaction;
use Illuminate\Support\Facades\Queue;

describe('Reconciliation Page', function () {
    beforeEach(function () {
        asUser();
    });

    it('can render the reconciliation page', function () {
        $this->get(Reconciliation::getUrl())
            ->assertSuccessful();
    });

    it('displays summary stats', function () {
        // Create matched transactions
        $bankFile = ImportedFile::factory()->completed()->create([
            'statement_type' => StatementType::Bank,
        ]);
        $invoiceFile = ImportedFile::factory()->completed()->create([
            'statement_type' => StatementType::Invoice,
        ]);

        Transaction::factory()->count(2)->create([
            'imported_file_id' => $bankFile->id,
            'reconciliation_status' => ReconciliationStatus::Matched,
        ]);
        Transaction::factory()->count(3)->create([
            'imported_file_id' => $bankFile->id,
            'reconciliation_status' => ReconciliationStatus::Flagged,
        ]);
        Transaction::factory()->count(1)->create([
            'imported_file_id' => $invoiceFile->id,
            'reconciliation_status' => ReconciliationStatus::Unreconciled,
        ]);

        $this->get(Reconciliation::getUrl())
            ->assertSuccessful()
            ->assertSee('Matched')
            ->assertSee('Flagged')
            ->assertSee('Unreconciled');
    });

    it('displays reconciliation matches in the table', function () {
        $bankFile = ImportedFile::factory()->completed()->create([
            'statement_type' => StatementType::Bank,
        ]);
        $invoiceFile = ImportedFile::factory()->completed()->create([
            'statement_type' => StatementType::Invoice,
        ]);

        $bankTxn = Transaction::factory()->debit(31900.00)->create([
            'imported_file_id' => $bankFile->id,
            'description' => 'NEFT-Assetpro Payment',
            'date' => '2025-04-15',
            'reconciliation_status' => ReconciliationStatus::Matched,
        ]);

        $invoiceTxn = Transaction::factory()->debit(31900.00)->create([
            'imported_file_id' => $invoiceFile->id,
            'description' => 'ASPL/2439 - Assetpro Solution',
            'date' => '2025-04-10',
            'reconciliation_status' => ReconciliationStatus::Matched,
        ]);

        ReconciliationMatch::factory()->create([
            'bank_transaction_id' => $bankTxn->id,
            'invoice_transaction_id' => $invoiceTxn->id,
            'match_method' => MatchMethod::Amount,
            'confidence' => 1.0,
        ]);

        $this->get(Reconciliation::getUrl())
            ->assertSuccessful();

        // Verify the match appears in the table
        expect(ReconciliationMatch::count())->toBe(1);
    });

    it('dispatches reconciliation job when run reconciliation action is triggered', function () {
        Queue::fake();

        $bankFile = ImportedFile::factory()->completed()->create([
            'statement_type' => StatementType::Bank,
            'status' => ImportStatus::Completed,
        ]);

        $invoiceFile = ImportedFile::factory()->completed()->create([
            'statement_type' => StatementType::Invoice,
            'status' => ImportStatus::Completed,
        ]);

        \Livewire\Livewire::test(Reconciliation::class)
            ->callAction('run_reconciliation', [
                'bank_file_id' => $bankFile->id,
                'invoice_file_id' => $invoiceFile->id,
            ]);

        Queue::assertPushed(ReconcileImportedFiles::class, function ($job) use ($bankFile, $invoiceFile) {
            return $job->bankFile->id === $bankFile->id
                && $job->invoiceFile->id === $invoiceFile->id;
        });
    });

    it('can unmatch a reconciliation match', function () {
        $bankFile = ImportedFile::factory()->completed()->create([
            'statement_type' => StatementType::Bank,
        ]);
        $invoiceFile = ImportedFile::factory()->completed()->create([
            'statement_type' => StatementType::Invoice,
        ]);

        $bankTxn = Transaction::factory()->debit(5000.00)->create([
            'imported_file_id' => $bankFile->id,
            'reconciliation_status' => ReconciliationStatus::Matched,
        ]);

        $invoiceTxn = Transaction::factory()->debit(5000.00)->create([
            'imported_file_id' => $invoiceFile->id,
            'reconciliation_status' => ReconciliationStatus::Matched,
        ]);

        $match = ReconciliationMatch::factory()->create([
            'bank_transaction_id' => $bankTxn->id,
            'invoice_transaction_id' => $invoiceTxn->id,
            'match_method' => MatchMethod::Amount,
            'confidence' => 1.0,
        ]);

        \Livewire\Livewire::test(Reconciliation::class)
            ->callTableAction('unmatch', $match);

        // The match should be soft-deleted
        expect(ReconciliationMatch::count())->toBe(0)
            ->and(ReconciliationMatch::withTrashed()->count())->toBe(1);

        // Transactions should be reset to unreconciled
        $bankTxn->refresh();
        $invoiceTxn->refresh();
        expect($bankTxn->reconciliation_status)->toBe(ReconciliationStatus::Unreconciled)
            ->and($invoiceTxn->reconciliation_status)->toBe(ReconciliationStatus::Unreconciled);
    });

    it('can create a manual match', function () {
        $bankFile = ImportedFile::factory()->completed()->create([
            'statement_type' => StatementType::Bank,
        ]);
        $invoiceFile = ImportedFile::factory()->completed()->create([
            'statement_type' => StatementType::Invoice,
        ]);

        $bankTxn = Transaction::factory()->debit(15000.00)->create([
            'imported_file_id' => $bankFile->id,
            'description' => 'NEFT-Manual Match Test',
            'date' => '2025-04-15',
            'reconciliation_status' => ReconciliationStatus::Unreconciled,
        ]);

        $invoiceTxn = Transaction::factory()->debit(15000.00)->create([
            'imported_file_id' => $invoiceFile->id,
            'description' => 'INV/999 - Test Vendor',
            'date' => '2025-04-10',
            'reconciliation_status' => ReconciliationStatus::Unreconciled,
            'raw_data' => ['vendor_name' => 'Test Vendor'],
        ]);

        \Livewire\Livewire::test(Reconciliation::class)
            ->callAction('manual_match', [
                'bank_transaction_id' => $bankTxn->id,
                'invoice_transaction_id' => $invoiceTxn->id,
            ]);

        // Match should be created
        expect(ReconciliationMatch::count())->toBe(1);

        $match = ReconciliationMatch::first();
        expect($match->match_method)->toBe(MatchMethod::Manual)
            ->and($match->confidence)->toBe(1.0);

        // Both transactions should be matched
        $bankTxn->refresh();
        $invoiceTxn->refresh();
        expect($bankTxn->reconciliation_status)->toBe(ReconciliationStatus::Matched)
            ->and($invoiceTxn->reconciliation_status)->toBe(ReconciliationStatus::Matched);
    });
});
