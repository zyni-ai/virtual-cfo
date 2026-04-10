<?php

use App\Models\AccountHead;
use App\Models\Company;
use App\Models\ImportedFile;
use App\Models\Transaction;
use App\Models\TransactionAggregate;
use App\Services\AggregateService;

describe('AggregateService', function () {
    beforeEach(function () {
        asUser();
        $this->service = app(AggregateService::class);
    });

    describe('rebuild', function () {
        it('produces correct totals matching PHP-decrypted sums', function () {
            $company = tenant();

            Transaction::factory()->debit(1000.50)->create([
                'company_id' => $company->id,
                'date' => '2025-04-15',
            ]);
            Transaction::factory()->debit(500.25)->create([
                'company_id' => $company->id,
                'date' => '2025-04-20',
            ]);
            Transaction::factory()->credit(3000.75)->create([
                'company_id' => $company->id,
                'date' => '2025-04-10',
            ]);

            // Rebuild from scratch (replaces observer-created aggregates)
            $this->service->rebuild($company->id);

            $aggregate = TransactionAggregate::where('company_id', $company->id)
                ->where('year_month', '2025-04')
                ->first();

            expect($aggregate)->not->toBeNull()
                ->and((float) $aggregate->total_debit)->toBe(1500.75)
                ->and((float) $aggregate->total_credit)->toBe(3000.75)
                ->and($aggregate->transaction_count)->toBe(3);
        });

        it('groups by year_month correctly', function () {
            $company = tenant();

            Transaction::factory()->debit(1000)->create([
                'company_id' => $company->id,
                'date' => '2025-03-15',
            ]);
            Transaction::factory()->debit(2000)->create([
                'company_id' => $company->id,
                'date' => '2025-04-15',
            ]);

            $this->service->rebuild($company->id);

            $marchAggregate = TransactionAggregate::where('company_id', $company->id)
                ->where('year_month', '2025-03')
                ->first();

            $aprilAggregate = TransactionAggregate::where('company_id', $company->id)
                ->where('year_month', '2025-04')
                ->first();

            expect((float) $marchAggregate->total_debit)->toBe(1000.0)
                ->and((float) $aprilAggregate->total_debit)->toBe(2000.0);
        });

        it('groups by account_head_id correctly', function () {
            $company = tenant();
            $head1 = AccountHead::factory()->create(['company_id' => $company->id]);
            $head2 = AccountHead::factory()->create(['company_id' => $company->id]);

            Transaction::factory()->debit(1000)->mapped($head1)->create([
                'company_id' => $company->id,
                'date' => '2025-04-15',
            ]);
            Transaction::factory()->debit(2000)->mapped($head2)->create([
                'company_id' => $company->id,
                'date' => '2025-04-15',
            ]);

            $this->service->rebuild($company->id);

            $agg1 = TransactionAggregate::where('company_id', $company->id)
                ->where('account_head_id', $head1->id)
                ->where('year_month', '2025-04')
                ->first();

            $agg2 = TransactionAggregate::where('company_id', $company->id)
                ->where('account_head_id', $head2->id)
                ->where('year_month', '2025-04')
                ->first();

            expect((float) $agg1->total_debit)->toBe(1000.0)
                ->and((float) $agg2->total_debit)->toBe(2000.0);
        });

        it('filters by company when companyId is provided', function () {
            $company1 = tenant();
            $company2 = Company::factory()->create();

            Transaction::factory()->debit(1000)->create([
                'company_id' => $company1->id,
                'date' => '2025-04-15',
            ]);

            Transaction::withoutEvents(function () use ($company2) {
                Transaction::factory()->debit(2000)->create([
                    'company_id' => $company2->id,
                    'date' => '2025-04-15',
                ]);
            });

            $this->service->rebuild($company1->id);

            $agg1 = TransactionAggregate::where('company_id', $company1->id)->first();
            expect($agg1)->not->toBeNull();

            // Company 2 had no observer and no rebuild, so no aggregates
            $agg2 = TransactionAggregate::where('company_id', $company2->id)->first();
            expect($agg2)->toBeNull();
        });

        it('filters by month when yearMonth is provided', function () {
            $company = tenant();

            Transaction::factory()->debit(1000)->create([
                'company_id' => $company->id,
                'date' => '2025-03-15',
            ]);
            Transaction::factory()->debit(2000)->create([
                'company_id' => $company->id,
                'date' => '2025-04-15',
            ]);

            // Rebuild only April
            $this->service->rebuild($company->id, '2025-04');

            // April was rebuilt
            $aprilAgg = TransactionAggregate::where('company_id', $company->id)
                ->where('year_month', '2025-04')
                ->first();
            expect((float) $aprilAgg->total_debit)->toBe(2000.0);

            // March aggregates from the observer should still exist
            $marchAgg = TransactionAggregate::where('company_id', $company->id)
                ->where('year_month', '2025-03')
                ->first();
            expect($marchAgg)->not->toBeNull();
        });
    });

    describe('rebuildForFile', function () {
        it('rebuilds aggregates for a file so stale null-head rows are replaced with real account heads', function () {
            $company = tenant();
            $head = AccountHead::factory()->create(['company_id' => $company->id]);

            $file = ImportedFile::factory()->create(['company_id' => $company->id]);

            // Create transaction with the real account_head_id but WITHOUT firing the observer
            // so we can manually insert a stale aggregate (simulating the bug: aggregate was
            // written at transaction creation time when account_head_id was null).
            Transaction::withoutEvents(function () use ($company, $file, $head) {
                Transaction::factory()->debit(5000)->create([
                    'company_id' => $company->id,
                    'imported_file_id' => $file->id,
                    'account_head_id' => $head->id,
                    'date' => '2025-04-15',
                ]);
            });

            // Insert a stale aggregate with null account_head_id — this mirrors what the
            // observer writes at transaction creation time (before head matching runs).
            TransactionAggregate::insert([
                'company_id' => $company->id,
                'account_head_id' => null,
                'bank_account_id' => null,
                'credit_card_id' => null,
                'year_month' => '2025-04',
                'total_debit' => 5000,
                'total_credit' => 0,
                'transaction_count' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Confirm stale state: aggregate has null account_head_id
            expect(TransactionAggregate::where('company_id', $company->id)
                ->where('year_month', '2025-04')
                ->whereNull('account_head_id')
                ->exists()
            )->toBeTrue();

            $this->service->rebuildForFile($file);

            // After rebuild: null-head aggregate is gone, real head aggregate exists
            expect(TransactionAggregate::where('company_id', $company->id)
                ->where('year_month', '2025-04')
                ->whereNull('account_head_id')
                ->exists()
            )->toBeFalse()
                ->and(TransactionAggregate::where('company_id', $company->id)
                    ->where('year_month', '2025-04')
                    ->where('account_head_id', $head->id)
                    ->value('total_debit')
                )->toBe('5000.00');
        });

        it('handles a file whose transactions span multiple months', function () {
            $company = tenant();
            $head = AccountHead::factory()->create(['company_id' => $company->id]);
            $file = ImportedFile::factory()->create(['company_id' => $company->id]);

            Transaction::withoutEvents(function () use ($company, $file, $head) {
                Transaction::factory()->debit(1000)->create([
                    'company_id' => $company->id,
                    'imported_file_id' => $file->id,
                    'account_head_id' => $head->id,
                    'date' => '2025-03-20',
                ]);
                Transaction::factory()->debit(2000)->create([
                    'company_id' => $company->id,
                    'imported_file_id' => $file->id,
                    'account_head_id' => $head->id,
                    'date' => '2025-04-05',
                ]);
            });

            $this->service->rebuildForFile($file);

            expect(TransactionAggregate::where('company_id', $company->id)
                ->where('account_head_id', $head->id)
                ->where('year_month', '2025-03')
                ->value('total_debit')
            )->toBe('1000.00')
                ->and(TransactionAggregate::where('company_id', $company->id)
                    ->where('account_head_id', $head->id)
                    ->where('year_month', '2025-04')
                    ->value('total_debit')
                )->toBe('2000.00');
        });

        it('does nothing when the file has no transactions', function () {
            $company = tenant();
            $file = ImportedFile::factory()->create(['company_id' => $company->id]);

            // Should not throw
            $this->service->rebuildForFile($file);

            expect(TransactionAggregate::where('company_id', $company->id)->count())->toBe(0);
        });
    });

    describe('observer integration', function () {
        it('increments aggregate when transaction is created', function () {
            $company = tenant();

            Transaction::factory()->debit(1500)->create([
                'company_id' => $company->id,
                'date' => '2025-04-15',
            ]);

            $aggregate = TransactionAggregate::where('company_id', $company->id)
                ->where('year_month', '2025-04')
                ->first();

            expect($aggregate)->not->toBeNull()
                ->and((float) $aggregate->total_debit)->toBe(1500.0)
                ->and($aggregate->transaction_count)->toBe(1);
        });

        it('aggregates multiple transactions in same month', function () {
            $company = tenant();

            Transaction::factory()->debit(1000)->create([
                'company_id' => $company->id,
                'date' => '2025-04-10',
            ]);
            Transaction::factory()->credit(2000)->create([
                'company_id' => $company->id,
                'date' => '2025-04-20',
            ]);

            // Both unmapped with no bank_account_id, so they share the same aggregate row
            $aggregates = TransactionAggregate::where('company_id', $company->id)
                ->where('year_month', '2025-04')
                ->get();

            $totalDebit = $aggregates->sum(fn ($a) => (float) $a->total_debit);
            $totalCredit = $aggregates->sum(fn ($a) => (float) $a->total_credit);
            $totalCount = $aggregates->sum('transaction_count');

            expect($totalDebit)->toBe(1000.0)
                ->and($totalCredit)->toBe(2000.0)
                ->and($totalCount)->toBe(2);
        });

        it('decrements aggregate when transaction is deleted', function () {
            $company = tenant();

            $transaction1 = Transaction::factory()->debit(1000)->create([
                'company_id' => $company->id,
                'date' => '2025-04-15',
            ]);
            Transaction::factory()->debit(500)->create([
                'company_id' => $company->id,
                'date' => '2025-04-15',
            ]);

            $transaction1->delete();

            $aggregate = TransactionAggregate::where('company_id', $company->id)
                ->where('year_month', '2025-04')
                ->first();

            expect((float) $aggregate->total_debit)->toBe(500.0)
                ->and($aggregate->transaction_count)->toBe(1);
        });

        it('adjusts aggregates when account_head changes', function () {
            $company = tenant();
            $head1 = AccountHead::factory()->create(['company_id' => $company->id]);
            $head2 = AccountHead::factory()->create(['company_id' => $company->id]);

            $transaction = Transaction::factory()->debit(1000)->mapped($head1)->create([
                'company_id' => $company->id,
                'date' => '2025-04-15',
            ]);

            $transaction->update(['account_head_id' => $head2->id]);

            $agg1 = TransactionAggregate::where('company_id', $company->id)
                ->where('account_head_id', $head1->id)
                ->where('year_month', '2025-04')
                ->first();

            $agg2 = TransactionAggregate::where('company_id', $company->id)
                ->where('account_head_id', $head2->id)
                ->where('year_month', '2025-04')
                ->first();

            expect((float) $agg1->total_debit)->toBe(0.0)
                ->and($agg1->transaction_count)->toBe(0)
                ->and((float) $agg2->total_debit)->toBe(1000.0)
                ->and($agg2->transaction_count)->toBe(1);
        });

        it('restores aggregate when soft-deleted transaction is restored', function () {
            $company = tenant();

            $transaction = Transaction::factory()->debit(1000)->create([
                'company_id' => $company->id,
                'date' => '2025-04-15',
            ]);

            $transaction->delete();
            $transaction->restore();

            $aggregate = TransactionAggregate::where('company_id', $company->id)
                ->where('year_month', '2025-04')
                ->first();

            expect((float) $aggregate->total_debit)->toBe(1000.0)
                ->and($aggregate->transaction_count)->toBe(1);
        });

        it('aggregates are tenant-scoped', function () {
            $company1 = tenant();
            $company2 = Company::factory()->create();

            Transaction::factory()->debit(1000)->create([
                'company_id' => $company1->id,
                'date' => '2025-04-15',
            ]);

            $company2Transaction = null;
            Transaction::withoutEvents(function () use ($company2, &$company2Transaction) {
                $company2Transaction = Transaction::factory()->debit(2000)->create([
                    'company_id' => $company2->id,
                    'date' => '2025-04-15',
                ]);
            });

            // Manually increment for company2 transaction
            $this->service->incrementForTransaction($company2Transaction);

            $agg1 = TransactionAggregate::where('company_id', $company1->id)->first();
            $agg2 = TransactionAggregate::where('company_id', $company2->id)->first();

            expect((float) $agg1->total_debit)->toBe(1000.0)
                ->and((float) $agg2->total_debit)->toBe(2000.0);
        });
    });
});
