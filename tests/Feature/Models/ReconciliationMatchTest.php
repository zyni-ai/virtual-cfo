<?php

use App\Enums\MatchMethod;
use App\Enums\ReconciliationStatus;
use App\Enums\StatementType;
use App\Models\ImportedFile;
use App\Models\ReconciliationMatch;
use App\Models\Transaction;

describe('ReconciliationMatch', function () {
    describe('relationships', function () {
        it('belongs to a bank transaction', function () {
            $bankFile = ImportedFile::factory()->completed()->create([
                'statement_type' => StatementType::Bank,
            ]);

            $bankTxn = Transaction::factory()->debit(5000.00)->create([
                'imported_file_id' => $bankFile->id,
            ]);

            $invoiceFile = ImportedFile::factory()->completed()->create([
                'statement_type' => StatementType::Invoice,
            ]);

            $invoiceTxn = Transaction::factory()->debit(5000.00)->create([
                'imported_file_id' => $invoiceFile->id,
            ]);

            $match = ReconciliationMatch::factory()->create([
                'bank_transaction_id' => $bankTxn->id,
                'invoice_transaction_id' => $invoiceTxn->id,
                'match_method' => MatchMethod::Amount,
                'confidence' => 1.0,
            ]);

            expect($match->bankTransaction)->toBeInstanceOf(Transaction::class)
                ->and($match->bankTransaction->id)->toBe($bankTxn->id);
        });

        it('belongs to an invoice transaction', function () {
            $bankFile = ImportedFile::factory()->completed()->create([
                'statement_type' => StatementType::Bank,
            ]);

            $bankTxn = Transaction::factory()->debit(5000.00)->create([
                'imported_file_id' => $bankFile->id,
            ]);

            $invoiceFile = ImportedFile::factory()->completed()->create([
                'statement_type' => StatementType::Invoice,
            ]);

            $invoiceTxn = Transaction::factory()->debit(5000.00)->create([
                'imported_file_id' => $invoiceFile->id,
            ]);

            $match = ReconciliationMatch::factory()->create([
                'bank_transaction_id' => $bankTxn->id,
                'invoice_transaction_id' => $invoiceTxn->id,
                'match_method' => MatchMethod::Amount,
                'confidence' => 1.0,
            ]);

            expect($match->invoiceTransaction)->toBeInstanceOf(Transaction::class)
                ->and($match->invoiceTransaction->id)->toBe($invoiceTxn->id);
        });
    });

    describe('casts', function () {
        it('casts match_method to MatchMethod enum', function () {
            $match = ReconciliationMatch::factory()->create([
                'match_method' => MatchMethod::Amount,
            ]);

            expect($match->match_method)->toBe(MatchMethod::Amount);
        });

        it('casts confidence to float', function () {
            $match = ReconciliationMatch::factory()->create([
                'confidence' => 0.95,
            ]);

            expect($match->confidence)->toBeFloat()
                ->and($match->confidence)->toBe(0.95);
        });
    });
});

describe('Transaction reconciliation', function () {
    it('has reconciliation_status defaulting to unreconciled', function () {
        $txn = Transaction::factory()->create();

        expect($txn->reconciliation_status)->toBe(ReconciliationStatus::Unreconciled);
    });

    it('casts reconciliation_status to ReconciliationStatus enum', function () {
        $txn = Transaction::factory()->create([
            'reconciliation_status' => ReconciliationStatus::Matched,
        ]);

        expect($txn->reconciliation_status)->toBe(ReconciliationStatus::Matched);
    });

    it('has reconciliationMatchesAsBank relationship', function () {
        $bankFile = ImportedFile::factory()->completed()->create([
            'statement_type' => StatementType::Bank,
        ]);

        $bankTxn = Transaction::factory()->debit(5000.00)->create([
            'imported_file_id' => $bankFile->id,
        ]);

        $invoiceFile = ImportedFile::factory()->completed()->create([
            'statement_type' => StatementType::Invoice,
        ]);

        $invoiceTxn = Transaction::factory()->debit(5000.00)->create([
            'imported_file_id' => $invoiceFile->id,
        ]);

        ReconciliationMatch::factory()->create([
            'bank_transaction_id' => $bankTxn->id,
            'invoice_transaction_id' => $invoiceTxn->id,
        ]);

        expect($bankTxn->reconciliationMatchesAsBank)->toHaveCount(1)
            ->and($bankTxn->reconciliationMatchesAsBank->first()->invoice_transaction_id)->toBe($invoiceTxn->id);
    });

    it('has reconciliationMatchesAsInvoice relationship', function () {
        $bankFile = ImportedFile::factory()->completed()->create([
            'statement_type' => StatementType::Bank,
        ]);

        $bankTxn = Transaction::factory()->debit(5000.00)->create([
            'imported_file_id' => $bankFile->id,
        ]);

        $invoiceFile = ImportedFile::factory()->completed()->create([
            'statement_type' => StatementType::Invoice,
        ]);

        $invoiceTxn = Transaction::factory()->debit(5000.00)->create([
            'imported_file_id' => $invoiceFile->id,
        ]);

        ReconciliationMatch::factory()->create([
            'bank_transaction_id' => $bankTxn->id,
            'invoice_transaction_id' => $invoiceTxn->id,
        ]);

        expect($invoiceTxn->reconciliationMatchesAsInvoice)->toHaveCount(1)
            ->and($invoiceTxn->reconciliationMatchesAsInvoice->first()->bank_transaction_id)->toBe($bankTxn->id);
    });

    it('scopes unreconciled transactions', function () {
        Transaction::factory()->create([
            'reconciliation_status' => ReconciliationStatus::Unreconciled,
        ]);
        Transaction::factory()->create([
            'reconciliation_status' => ReconciliationStatus::Matched,
        ]);

        expect(Transaction::unreconciled()->count())->toBe(1);
    });

    it('scopes flagged transactions', function () {
        Transaction::factory()->create([
            'reconciliation_status' => ReconciliationStatus::Flagged,
        ]);
        Transaction::factory()->create([
            'reconciliation_status' => ReconciliationStatus::Matched,
        ]);
        Transaction::factory()->create([
            'reconciliation_status' => ReconciliationStatus::Flagged,
        ]);

        expect(Transaction::flagged()->count())->toBe(2);
    });

    it('calculates amount from debit', function () {
        $txn = Transaction::factory()->debit(5000.00)->create();

        expect($txn->amount)->toBe(5000.0);
    });

    it('calculates amount from credit', function () {
        $txn = Transaction::factory()->credit(10000.00)->create();

        expect($txn->amount)->toBe(10000.0);
    });

    it('returns null amount when both debit and credit are null', function () {
        $txn = Transaction::factory()->create([
            'debit' => null,
            'credit' => null,
        ]);

        expect($txn->amount)->toBeNull();
    });
});
