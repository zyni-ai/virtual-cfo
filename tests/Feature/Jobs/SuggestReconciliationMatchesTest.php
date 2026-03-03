<?php

use App\Enums\MatchStatus;
use App\Enums\ReconciliationStatus;
use App\Enums\StatementType;
use App\Jobs\SuggestReconciliationMatches;
use App\Models\ImportedFile;
use App\Models\ReconciliationMatch;
use App\Models\Transaction;

describe('SuggestReconciliationMatches job', function () {
    beforeEach(function () {
        asUser();

        $this->bankFile = ImportedFile::factory()->completed(totalRows: 3, mappedRows: 0)->create([
            'company_id' => tenant()->id,
            'statement_type' => StatementType::Bank,
        ]);

        $this->invoiceFile = ImportedFile::factory()->completed(totalRows: 3, mappedRows: 0)->create([
            'company_id' => tenant()->id,
            'statement_type' => StatementType::Invoice,
        ]);
    });

    it('creates suggested matches for unreconciled bank transactions', function () {
        Transaction::factory()->debit(15000.00)->create([
            'company_id' => tenant()->id,
            'imported_file_id' => $this->bankFile->id,
            'description' => 'Payment to Vendor',
            'date' => '2025-04-15',
            'reconciliation_status' => ReconciliationStatus::Unreconciled,
        ]);

        Transaction::factory()->debit(15000.00)->create([
            'company_id' => tenant()->id,
            'imported_file_id' => $this->invoiceFile->id,
            'description' => 'Vendor Invoice',
            'date' => '2025-04-10',
            'reconciliation_status' => ReconciliationStatus::Unreconciled,
        ]);

        SuggestReconciliationMatches::dispatchSync($this->invoiceFile);

        expect(ReconciliationMatch::count())->toBe(1);

        $match = ReconciliationMatch::first();
        expect($match->status)->toBe(MatchStatus::Suggested);
    });

    it('does not modify transaction reconciliation status', function () {
        $bankTxn = Transaction::factory()->debit(5000.00)->create([
            'company_id' => tenant()->id,
            'imported_file_id' => $this->bankFile->id,
            'description' => 'Payment',
            'date' => '2025-04-15',
            'reconciliation_status' => ReconciliationStatus::Unreconciled,
        ]);

        Transaction::factory()->debit(5000.00)->create([
            'company_id' => tenant()->id,
            'imported_file_id' => $this->invoiceFile->id,
            'description' => 'Invoice',
            'date' => '2025-04-10',
            'reconciliation_status' => ReconciliationStatus::Unreconciled,
        ]);

        SuggestReconciliationMatches::dispatchSync($this->invoiceFile);

        $bankTxn->refresh();
        expect($bankTxn->reconciliation_status)->toBe(ReconciliationStatus::Unreconciled);
    });

    it('handles invoice file with no matching bank transactions', function () {
        Transaction::factory()->debit(99999.00)->create([
            'company_id' => tenant()->id,
            'imported_file_id' => $this->invoiceFile->id,
            'description' => 'Unmatched Invoice',
            'date' => '2025-04-10',
            'reconciliation_status' => ReconciliationStatus::Unreconciled,
        ]);

        SuggestReconciliationMatches::dispatchSync($this->invoiceFile);

        expect(ReconciliationMatch::count())->toBe(0);
    });

    it('is a queued job', function () {
        expect(class_implements(SuggestReconciliationMatches::class))
            ->toContain(Illuminate\Contracts\Queue\ShouldQueue::class);
    });
});
