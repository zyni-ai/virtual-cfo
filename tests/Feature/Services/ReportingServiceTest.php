<?php

use App\Models\AccountHead;
use App\Models\BankAccount;
use App\Models\CreditCard;
use App\Models\TransactionAggregate;
use App\Services\ReportingService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    asUser();
    $this->service = app(ReportingService::class);
});

describe('ReportingService', function () {
    describe('financialYearMonths', function () {
        it('returns 12 months from April to March for a date in the middle of the FY', function () {
            $months = $this->service->financialYearMonths(Carbon::create(2025, 8, 15));

            expect($months)->toHaveCount(12)
                ->and($months->first()->format('Y-m'))->toBe('2025-04')
                ->and($months->last()->format('Y-m'))->toBe('2026-03');
        });

        it('returns current FY when no reference date provided', function () {
            $months = $this->service->financialYearMonths();

            expect($months)->toHaveCount(12);

            // Current month should be in the range
            $yearMonths = $months->map->format('Y-m');
            expect($yearMonths)->toContain(now()->format('Y-m'));
        });

        it('handles January–March (falls in previous calendar year FY)', function () {
            $months = $this->service->financialYearMonths(Carbon::create(2026, 2, 1));

            expect($months->first()->format('Y-m'))->toBe('2025-04')
                ->and($months->last()->format('Y-m'))->toBe('2026-03');
        });

        it('handles April (start of new FY)', function () {
            $months = $this->service->financialYearMonths(Carbon::create(2025, 4, 1));

            expect($months->first()->format('Y-m'))->toBe('2025-04')
                ->and($months->last()->format('Y-m'))->toBe('2026-03');
        });

        it('uses company fy_start_month from tenant', function () {
            $company = tenant();
            $company->update(['fy_start_month' => 7]); // Australian FY: July–June

            $months = $this->service->financialYearMonths(Carbon::create(2025, 9, 15));

            expect($months)->toHaveCount(12)
                ->and($months->first()->format('Y-m'))->toBe('2025-07')
                ->and($months->last()->format('Y-m'))->toBe('2026-06');
        });

        it('uses January start month for calendar year FY', function () {
            $company = tenant();
            $company->update(['fy_start_month' => 1]); // US/France FY: Jan–Dec

            $months = $this->service->financialYearMonths(Carbon::create(2025, 6, 15));

            expect($months)->toHaveCount(12)
                ->and($months->first()->format('Y-m'))->toBe('2025-01')
                ->and($months->last()->format('Y-m'))->toBe('2025-12');
        });

        it('handles date before fy_start_month (previous FY)', function () {
            $company = tenant();
            $company->update(['fy_start_month' => 7]);

            // May 2025 is before July, so it belongs to FY starting July 2024
            $months = $this->service->financialYearMonths(Carbon::create(2025, 5, 15));

            expect($months->first()->format('Y-m'))->toBe('2024-07')
                ->and($months->last()->format('Y-m'))->toBe('2025-06');
        });
    });

    describe('filteredAggregatesQuery', function () {
        it('returns all aggregates for current tenant with no filters', function () {
            $company = tenant();
            $head = AccountHead::factory()->create(['company_id' => $company->id]);
            foreach (['2025-04', '2025-05', '2025-06'] as $month) {
                TransactionAggregate::factory()->create(['company_id' => $company->id, 'account_head_id' => $head->id, 'year_month' => $month]);
            }

            $results = $this->service->filteredAggregatesQuery()->get();

            expect($results)->toHaveCount(3);
        });

        it('filters by date range', function () {
            $company = tenant();
            TransactionAggregate::factory()->create(['company_id' => $company->id, 'year_month' => '2025-04']);
            TransactionAggregate::factory()->create(['company_id' => $company->id, 'year_month' => '2025-06']);
            TransactionAggregate::factory()->create(['company_id' => $company->id, 'year_month' => '2025-09']);

            $results = $this->service->filteredAggregatesQuery([
                'dateFrom' => '2025-05',
                'dateUntil' => '2025-07',
            ])->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->year_month)->toBe('2025-06');
        });

        it('filters by bank account IDs', function () {
            $company = tenant();
            $bank = BankAccount::factory()->create(['company_id' => $company->id]);
            TransactionAggregate::factory()->create(['company_id' => $company->id, 'bank_account_id' => $bank->id]);
            TransactionAggregate::factory()->create(['company_id' => $company->id, 'bank_account_id' => null]);

            $results = $this->service->filteredAggregatesQuery([
                'bankAccountIds' => [$bank->id],
            ])->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->bank_account_id)->toBe($bank->id);
        });

        it('filters by credit card IDs', function () {
            $company = tenant();
            $card = CreditCard::factory()->create(['company_id' => $company->id]);
            TransactionAggregate::factory()->create(['company_id' => $company->id, 'credit_card_id' => $card->id]);
            TransactionAggregate::factory()->create(['company_id' => $company->id, 'credit_card_id' => null]);

            $results = $this->service->filteredAggregatesQuery([
                'creditCardIds' => [$card->id],
            ])->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->credit_card_id)->toBe($card->id);
        });

        it('filters by account head IDs', function () {
            $company = tenant();
            $head = AccountHead::factory()->create(['company_id' => $company->id]);
            TransactionAggregate::factory()->create(['company_id' => $company->id, 'account_head_id' => $head->id]);
            TransactionAggregate::factory()->create(['company_id' => $company->id, 'account_head_id' => null]);

            $results = $this->service->filteredAggregatesQuery([
                'accountHeadIds' => [$head->id],
            ])->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->account_head_id)->toBe($head->id);
        });

        it('does not return aggregates from other companies', function () {
            $company = tenant();
            TransactionAggregate::factory()->create(['company_id' => $company->id]);

            // Other company aggregate (created before tenant context)
            $otherCompany = \App\Models\Company::factory()->create();
            TransactionAggregate::factory()->create(['company_id' => $otherCompany->id]);

            $results = $this->service->filteredAggregatesQuery()->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->company_id)->toBe($company->id);
        });
    });

    describe('monthlyTotalsByHead', function () {
        it('returns top N heads with monthly debit totals', function () {
            $company = tenant();
            $head1 = AccountHead::factory()->create(['company_id' => $company->id, 'name' => 'Rent']);
            $head2 = AccountHead::factory()->create(['company_id' => $company->id, 'name' => 'Utilities']);

            TransactionAggregate::factory()->create([
                'company_id' => $company->id,
                'account_head_id' => $head1->id,
                'year_month' => '2025-04',
                'total_debit' => 50000.00,
            ]);
            TransactionAggregate::factory()->create([
                'company_id' => $company->id,
                'account_head_id' => $head1->id,
                'year_month' => '2025-05',
                'total_debit' => 50000.00,
            ]);
            TransactionAggregate::factory()->create([
                'company_id' => $company->id,
                'account_head_id' => $head2->id,
                'year_month' => '2025-04',
                'total_debit' => 5000.00,
            ]);

            $result = $this->service->monthlyTotalsByHead([], 10);

            expect($result)->toHaveKey('heads')
                ->and($result)->toHaveKey('months')
                ->and($result['heads'])->toHaveCount(2);

            // Head1 (Rent) should be first (higher total)
            expect($result['heads'][0]['name'])->toBe('Rent')
                ->and($result['heads'][0]['data']['2025-04'])->toBe(50000.00)
                ->and($result['heads'][0]['data']['2025-05'])->toBe(50000.00);

            expect($result['heads'][1]['name'])->toBe('Utilities')
                ->and($result['heads'][1]['data']['2025-04'])->toBe(5000.00);
        });

        it('limits to top N heads', function () {
            $company = tenant();

            for ($i = 1; $i <= 5; $i++) {
                $head = AccountHead::factory()->create(['company_id' => $company->id]);
                TransactionAggregate::factory()->create([
                    'company_id' => $company->id,
                    'account_head_id' => $head->id,
                    'year_month' => '2025-04',
                    'total_debit' => (6 - $i) * 10000,
                ]);
            }

            $result = $this->service->monthlyTotalsByHead([], 3);

            expect($result['heads'])->toHaveCount(3);
        });

        it('excludes null account_head_id aggregates', function () {
            $company = tenant();
            TransactionAggregate::factory()->create([
                'company_id' => $company->id,
                'account_head_id' => null,
                'year_month' => '2025-04',
                'total_debit' => 100000.00,
            ]);

            $result = $this->service->monthlyTotalsByHead();

            expect($result['heads'])->toBeEmpty();
        });
    });

    describe('periodComparison', function () {
        it('compares two periods with percentage change', function () {
            $company = tenant();
            $head = AccountHead::factory()->create(['company_id' => $company->id, 'name' => 'Rent']);

            TransactionAggregate::factory()->create([
                'company_id' => $company->id,
                'account_head_id' => $head->id,
                'year_month' => '2025-04',
                'total_debit' => 50000.00,
            ]);
            TransactionAggregate::factory()->create([
                'company_id' => $company->id,
                'account_head_id' => $head->id,
                'year_month' => '2025-05',
                'total_debit' => 55000.00,
            ]);

            $result = $this->service->periodComparison(
                periodA: ['2025-04'],
                periodB: ['2025-05'],
            );

            expect($result)->toHaveCount(1)
                ->and($result[0]['head_name'])->toBe('Rent')
                ->and($result[0]['period_a_total'])->toBe(50000.00)
                ->and($result[0]['period_b_total'])->toBe(55000.00)
                ->and($result[0]['change_percent'])->toBe(10.0);
        });

        it('handles zero period A total gracefully', function () {
            $company = tenant();
            $head = AccountHead::factory()->create(['company_id' => $company->id, 'name' => 'New Expense']);

            TransactionAggregate::factory()->create([
                'company_id' => $company->id,
                'account_head_id' => $head->id,
                'year_month' => '2025-05',
                'total_debit' => 10000.00,
            ]);

            $result = $this->service->periodComparison(
                periodA: ['2025-04'],
                periodB: ['2025-05'],
            );

            expect($result)->toHaveCount(1)
                ->and($result[0]['period_a_total'])->toBe(0.0)
                ->and($result[0]['period_b_total'])->toBe(10000.00)
                ->and($result[0]['change_percent'])->toBeNull();
        });

        it('handles multi-month periods', function () {
            $company = tenant();
            $head = AccountHead::factory()->create(['company_id' => $company->id, 'name' => 'Rent']);

            TransactionAggregate::factory()->create(['company_id' => $company->id, 'account_head_id' => $head->id, 'year_month' => '2025-04', 'total_debit' => 10000]);
            TransactionAggregate::factory()->create(['company_id' => $company->id, 'account_head_id' => $head->id, 'year_month' => '2025-05', 'total_debit' => 10000]);
            TransactionAggregate::factory()->create(['company_id' => $company->id, 'account_head_id' => $head->id, 'year_month' => '2025-06', 'total_debit' => 10000]);

            TransactionAggregate::factory()->create(['company_id' => $company->id, 'account_head_id' => $head->id, 'year_month' => '2025-07', 'total_debit' => 15000]);
            TransactionAggregate::factory()->create(['company_id' => $company->id, 'account_head_id' => $head->id, 'year_month' => '2025-08', 'total_debit' => 15000]);
            TransactionAggregate::factory()->create(['company_id' => $company->id, 'account_head_id' => $head->id, 'year_month' => '2025-09', 'total_debit' => 15000]);

            $result = $this->service->periodComparison(
                periodA: ['2025-04', '2025-05', '2025-06'],
                periodB: ['2025-07', '2025-08', '2025-09'],
            );

            expect($result[0]['period_a_total'])->toBe(30000.00)
                ->and($result[0]['period_b_total'])->toBe(45000.00)
                ->and($result[0]['change_percent'])->toBe(50.0);
        });
    });

    describe('sourceBreakdown', function () {
        it('groups totals by head and source', function () {
            $company = tenant();
            $head = AccountHead::factory()->create(['company_id' => $company->id, 'name' => 'Rent']);
            $bank = BankAccount::factory()->create(['company_id' => $company->id, 'name' => 'HDFC Current']);
            $card = CreditCard::factory()->create(['company_id' => $company->id, 'name' => 'ICICI CC']);

            TransactionAggregate::factory()->create([
                'company_id' => $company->id,
                'account_head_id' => $head->id,
                'bank_account_id' => $bank->id,
                'credit_card_id' => null,
                'year_month' => '2025-04',
                'total_debit' => 30000.00,
            ]);
            TransactionAggregate::factory()->create([
                'company_id' => $company->id,
                'account_head_id' => $head->id,
                'bank_account_id' => null,
                'credit_card_id' => $card->id,
                'year_month' => '2025-04',
                'total_debit' => 20000.00,
            ]);

            $result = $this->service->sourceBreakdown();

            expect($result)->toHaveCount(1)
                ->and($result[0]['head_name'])->toBe('Rent')
                ->and($result[0]['sources'])->toHaveCount(2);

            $sourceNames = collect($result[0]['sources'])->pluck('source_name')->all();
            expect($sourceNames)->toContain('HDFC Current')
                ->toContain('ICICI CC');
        });

        it('excludes null account_head_id', function () {
            $company = tenant();
            TransactionAggregate::factory()->create([
                'company_id' => $company->id,
                'account_head_id' => null,
                'year_month' => '2025-04',
                'total_debit' => 100000.00,
            ]);

            $result = $this->service->sourceBreakdown();

            expect($result)->toBeEmpty();
        });
    });

    describe('expenseSummary', function () {
        it('returns pivot table with heads as rows and months as columns', function () {
            $company = tenant();
            $head1 = AccountHead::factory()->create(['company_id' => $company->id, 'name' => 'Rent']);
            $head2 = AccountHead::factory()->create(['company_id' => $company->id, 'name' => 'Utilities']);

            TransactionAggregate::factory()->create(['company_id' => $company->id, 'account_head_id' => $head1->id, 'year_month' => '2025-04', 'total_debit' => 50000]);
            TransactionAggregate::factory()->create(['company_id' => $company->id, 'account_head_id' => $head1->id, 'year_month' => '2025-05', 'total_debit' => 50000]);
            TransactionAggregate::factory()->create(['company_id' => $company->id, 'account_head_id' => $head2->id, 'year_month' => '2025-04', 'total_debit' => 5000]);

            $result = $this->service->expenseSummary();

            expect($result)->toHaveKey('rows')
                ->and($result)->toHaveKey('months')
                ->and($result)->toHaveKey('grand_total')
                ->and($result['rows'])->toHaveCount(2);

            $rentRow = collect($result['rows'])->firstWhere('head_name', 'Rent');
            expect($rentRow)->not->toBeNull()
                ->and($rentRow['row_total'])->toBe(100000.00)
                ->and($rentRow['monthly']['2025-04'])->toBe(50000.00)
                ->and($rentRow['monthly']['2025-05'])->toBe(50000.00);

            expect($rentRow['percent_of_total'])->toBeGreaterThan(0);
        });

        it('calculates grand total correctly', function () {
            $company = tenant();
            $head = AccountHead::factory()->create(['company_id' => $company->id]);
            TransactionAggregate::factory()->create(['company_id' => $company->id, 'account_head_id' => $head->id, 'year_month' => '2025-04', 'total_debit' => 10000]);
            TransactionAggregate::factory()->create(['company_id' => $company->id, 'account_head_id' => $head->id, 'year_month' => '2025-05', 'total_debit' => 20000]);

            $result = $this->service->expenseSummary();

            expect($result['grand_total'])->toBe(30000.00);
        });

        it('returns empty rows when no aggregates exist', function () {
            $result = $this->service->expenseSummary();

            expect($result['rows'])->toBeEmpty()
                ->and($result['grand_total'])->toBe(0.0);
        });
    });
});
