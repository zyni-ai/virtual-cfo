<?php

use App\Enums\MatchMethod;
use App\Enums\MatchStatus;
use App\Enums\ReconciliationStatus;
use App\Enums\StatementType;
use App\Filament\Pages\Reconciliation;
use App\Filament\Widgets\ReconciliationStatsOverview;
use App\Jobs\ReconcileImportedFiles;
use App\Models\ImportedFile;
use App\Models\ReconciliationMatch;
use App\Models\Transaction;
use Illuminate\Support\Facades\Queue;

use function Pest\Livewire\livewire;

describe('Reconciliation Page', function () {
    beforeEach(function () {
        asUser();
    });

    it('can render the reconciliation page', function () {
        $this->get(Reconciliation::getUrl())
            ->assertSuccessful();
    });

    it('displays summary stats via widget', function () {
        $bankFile = ImportedFile::factory()->completed()->create([
            'statement_type' => StatementType::Bank,
        ]);

        Transaction::factory()->count(2)->create([
            'imported_file_id' => $bankFile->id,
            'reconciliation_status' => ReconciliationStatus::Matched,
        ]);
        Transaction::factory()->count(3)->create([
            'imported_file_id' => $bankFile->id,
            'reconciliation_status' => ReconciliationStatus::Flagged,
        ]);
        Transaction::factory()->count(5)->create([
            'imported_file_id' => $bankFile->id,
            'reconciliation_status' => ReconciliationStatus::Unreconciled,
        ]);

        livewire(ReconciliationStatsOverview::class)
            ->assertSee('Unreconciled')
            ->assertSee('Matched')
            ->assertSee('Flagged')
            ->assertSee('Total Matches');
    });

    it('registers the stats widget as a header widget', function () {
        $page = new Reconciliation;
        $widgets = $page->getHeaderWidgets();

        expect($widgets)->toContain(ReconciliationStatsOverview::class);
    });

    it('displays bank transactions in the table', function () {
        $bankFile = ImportedFile::factory()->completed()->create([
            'statement_type' => StatementType::Bank,
        ]);

        $transactions = Transaction::factory()->count(3)->create([
            'imported_file_id' => $bankFile->id,
            'reconciliation_status' => ReconciliationStatus::Unreconciled,
        ]);

        livewire(Reconciliation::class)
            ->assertCanSeeTableRecords($transactions)
            ->assertCountTableRecords(3);
    });

    it('excludes invoice transactions from the table', function () {
        $bankFile = ImportedFile::factory()->completed()->create([
            'statement_type' => StatementType::Bank,
        ]);
        $invoiceFile = ImportedFile::factory()->completed()->create([
            'statement_type' => StatementType::Invoice,
        ]);

        $bankTxn = Transaction::factory()->create([
            'imported_file_id' => $bankFile->id,
        ]);
        $invoiceTxn = Transaction::factory()->create([
            'imported_file_id' => $invoiceFile->id,
        ]);

        livewire(Reconciliation::class)
            ->assertCanSeeTableRecords([$bankTxn])
            ->assertCanNotSeeTableRecords([$invoiceTxn]);
    });

    it('can filter by reconciliation status', function () {
        $bankFile = ImportedFile::factory()->completed()->create([
            'statement_type' => StatementType::Bank,
        ]);

        $unreconciled = Transaction::factory()->create([
            'imported_file_id' => $bankFile->id,
            'reconciliation_status' => ReconciliationStatus::Unreconciled,
        ]);
        $matched = Transaction::factory()->create([
            'imported_file_id' => $bankFile->id,
            'reconciliation_status' => ReconciliationStatus::Matched,
        ]);
        $flagged = Transaction::factory()->create([
            'imported_file_id' => $bankFile->id,
            'reconciliation_status' => ReconciliationStatus::Flagged,
        ]);

        livewire(Reconciliation::class)
            ->assertCanSeeTableRecords([$unreconciled, $matched, $flagged])
            ->filterTable('reconciliation_status', ReconciliationStatus::Unreconciled->value)
            ->assertCanSeeTableRecords([$unreconciled])
            ->assertCanNotSeeTableRecords([$matched, $flagged]);
    });

    it('dispatches reconciliation job when run reconciliation action is triggered', function () {
        Queue::fake();

        $bankFile = ImportedFile::factory()->completed()->create([
            'statement_type' => StatementType::Bank,
        ]);

        $invoiceFile = ImportedFile::factory()->completed()->create([
            'statement_type' => StatementType::Invoice,
        ]);

        livewire(Reconciliation::class)
            ->callAction('run_reconciliation', [
                'bank_file_id' => $bankFile->id,
                'invoice_file_id' => $invoiceFile->id,
            ]);

        Queue::assertPushed(ReconcileImportedFiles::class, function ($job) use ($bankFile, $invoiceFile) {
            return $job->bankFile->id === $bankFile->id
                && $job->invoiceFile->id === $invoiceFile->id;
        });
    });

    it('can create a manual match with confirmed status', function () {
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

        livewire(Reconciliation::class)
            ->callAction('manual_match', [
                'bank_transaction_id' => $bankTxn->id,
                'invoice_transaction_id' => $invoiceTxn->id,
            ]);

        expect(ReconciliationMatch::count())->toBe(1);

        $match = ReconciliationMatch::first();
        expect($match->match_method)->toBe(MatchMethod::Manual)
            ->and($match->confidence)->toBe(1.0)
            ->and($match->status)->toBe(MatchStatus::Confirmed);

        $bankTxn->refresh();
        $invoiceTxn->refresh();
        expect($bankTxn->reconciliation_status)->toBe(ReconciliationStatus::Matched)
            ->and($invoiceTxn->reconciliation_status)->toBe(ReconciliationStatus::Matched);
    });

    it('displays pending suggestions count in stats widget', function () {
        $bankFile = ImportedFile::factory()->completed()->create([
            'statement_type' => StatementType::Bank,
        ]);
        $invoiceFile = ImportedFile::factory()->completed()->create([
            'statement_type' => StatementType::Invoice,
        ]);

        $bankTxn = Transaction::factory()->create([
            'imported_file_id' => $bankFile->id,
            'reconciliation_status' => ReconciliationStatus::Unreconciled,
        ]);
        $invoiceTxn = Transaction::factory()->create([
            'imported_file_id' => $invoiceFile->id,
            'reconciliation_status' => ReconciliationStatus::Unreconciled,
        ]);

        ReconciliationMatch::factory()->suggested()->create([
            'bank_transaction_id' => $bankTxn->id,
            'invoice_transaction_id' => $invoiceTxn->id,
        ]);

        livewire(ReconciliationStatsOverview::class)
            ->assertSee('Pending Suggestions');
    });

    it('can confirm a suggested match via table action', function () {
        $bankFile = ImportedFile::factory()->completed()->create([
            'statement_type' => StatementType::Bank,
        ]);
        $invoiceFile = ImportedFile::factory()->completed()->create([
            'statement_type' => StatementType::Invoice,
        ]);

        $bankTxn = Transaction::factory()->debit(5000.00)->create([
            'imported_file_id' => $bankFile->id,
            'reconciliation_status' => ReconciliationStatus::Unreconciled,
        ]);

        $invoiceTxn = Transaction::factory()->debit(5000.00)->create([
            'imported_file_id' => $invoiceFile->id,
            'reconciliation_status' => ReconciliationStatus::Unreconciled,
        ]);

        $match = ReconciliationMatch::factory()->suggested()->create([
            'bank_transaction_id' => $bankTxn->id,
            'invoice_transaction_id' => $invoiceTxn->id,
        ]);

        livewire(Reconciliation::class)
            ->callTableAction('confirm_suggestion', $bankTxn);

        $match->refresh();
        $bankTxn->refresh();
        expect($match->status)->toBe(MatchStatus::Confirmed)
            ->and($bankTxn->reconciliation_status)->toBe(ReconciliationStatus::Matched);
    });

    it('can reject all suggestions for a bank transaction via table action', function () {
        $bankFile = ImportedFile::factory()->completed()->create([
            'statement_type' => StatementType::Bank,
        ]);
        $invoiceFile = ImportedFile::factory()->completed()->create([
            'statement_type' => StatementType::Invoice,
        ]);

        $bankTxn = Transaction::factory()->debit(5000.00)->create([
            'imported_file_id' => $bankFile->id,
            'reconciliation_status' => ReconciliationStatus::Unreconciled,
        ]);

        $match1 = ReconciliationMatch::factory()->suggested()->create([
            'bank_transaction_id' => $bankTxn->id,
            'invoice_transaction_id' => Transaction::factory()->create([
                'imported_file_id' => $invoiceFile->id,
            ])->id,
        ]);
        $match2 = ReconciliationMatch::factory()->suggested()->create([
            'bank_transaction_id' => $bankTxn->id,
            'invoice_transaction_id' => Transaction::factory()->create([
                'imported_file_id' => $invoiceFile->id,
            ])->id,
        ]);

        livewire(Reconciliation::class)
            ->callTableAction('reject_suggestions', $bankTxn);

        $match1->refresh();
        $match2->refresh();
        expect($match1->status)->toBe(MatchStatus::Rejected)
            ->and($match2->status)->toBe(MatchStatus::Rejected);
    });
});

describe('Transaction::scopeMatched', function () {
    it('returns only matched transactions', function () {
        $bankFile = ImportedFile::factory()->completed()->create([
            'statement_type' => StatementType::Bank,
        ]);

        $matched = Transaction::factory()->create([
            'imported_file_id' => $bankFile->id,
            'reconciliation_status' => ReconciliationStatus::Matched,
        ]);
        Transaction::factory()->create([
            'imported_file_id' => $bankFile->id,
            'reconciliation_status' => ReconciliationStatus::Unreconciled,
        ]);

        $results = Transaction::matched()->get();

        expect($results)->toHaveCount(1)
            ->and($results->first()->id)->toBe($matched->id);
    });
});
