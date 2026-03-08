<?php

use App\Enums\PeriodType;
use App\Models\AccountHead;
use App\Models\Budget;
use App\Models\TransactionAggregate;
use App\Services\BudgetService;

describe('BudgetService', function () {
    beforeEach(function () {
        asUser();
        $this->company = tenant();
        $this->service = new BudgetService;
    });

    describe('getActualSpend', function () {
        it('calculates monthly spend from aggregates', function () {
            $head = AccountHead::factory()->for($this->company)->create();
            $budget = Budget::factory()->for($this->company)->create([
                'account_head_id' => $head->id,
                'period_type' => PeriodType::Monthly,
                'year_month' => '2026-03',
                'amount' => 100000,
            ]);

            TransactionAggregate::factory()->create([
                'company_id' => $this->company->id,
                'account_head_id' => $head->id,
                'year_month' => '2026-03',
                'total_debit' => 45000.00,
                'total_credit' => 0,
            ]);

            $actual = $this->service->getActualSpend($budget, '2026-03');
            expect($actual)->toBe(45000.0);
        });

        it('calculates quarterly spend by summing 3 months', function () {
            $head = AccountHead::factory()->for($this->company)->create();
            $budget = Budget::factory()->for($this->company)->quarterly()->create([
                'account_head_id' => $head->id,
                'year_month' => '2026-Q1',
                'amount' => 300000,
            ]);

            TransactionAggregate::factory()->create([
                'company_id' => $this->company->id,
                'account_head_id' => $head->id,
                'year_month' => '2026-01',
                'total_debit' => 80000,
            ]);
            TransactionAggregate::factory()->create([
                'company_id' => $this->company->id,
                'account_head_id' => $head->id,
                'year_month' => '2026-02',
                'total_debit' => 90000,
            ]);
            TransactionAggregate::factory()->create([
                'company_id' => $this->company->id,
                'account_head_id' => $head->id,
                'year_month' => '2026-03',
                'total_debit' => 70000,
            ]);

            $actual = $this->service->getActualSpend($budget);
            expect($actual)->toBe(240000.0);
        });

        it('calculates annual spend across financial year', function () {
            $head = AccountHead::factory()->for($this->company)->create();
            $budget = Budget::factory()->annual()->create([
                'company_id' => $this->company->id,
                'account_head_id' => $head->id,
                'financial_year' => '2025-26',
                'amount' => 1000000,
            ]);

            // April 2025
            TransactionAggregate::factory()->create([
                'company_id' => $this->company->id,
                'account_head_id' => $head->id,
                'year_month' => '2025-04',
                'total_debit' => 50000,
            ]);
            // March 2026
            TransactionAggregate::factory()->create([
                'company_id' => $this->company->id,
                'account_head_id' => $head->id,
                'year_month' => '2026-03',
                'total_debit' => 60000,
            ]);

            $actual = $this->service->getActualSpend($budget);
            expect($actual)->toBe(110000.0);
        });
    });

    describe('getBudgetStatuses', function () {
        it('returns status for all active budgets', function () {
            $head = AccountHead::factory()->for($this->company)->create();
            Budget::factory()->for($this->company)->create([
                'account_head_id' => $head->id,
                'period_type' => PeriodType::Monthly,
                'year_month' => '2026-03',
                'amount' => 100000,
            ]);

            TransactionAggregate::factory()->create([
                'company_id' => $this->company->id,
                'account_head_id' => $head->id,
                'year_month' => '2026-03',
                'total_debit' => 85000,
            ]);

            $statuses = $this->service->getBudgetStatuses($this->company, '2026-03');
            expect($statuses)->toHaveCount(1);

            $status = $statuses->first();
            expect($status['actual'])->toBe(85000.0)
                ->and($status['percentage'])->toBe(85.0)
                ->and($status['status'])->toBe('warning');
        });

        it('excludes inactive budgets', function () {
            $head = AccountHead::factory()->for($this->company)->create();
            Budget::factory()->for($this->company)->inactive()->create([
                'account_head_id' => $head->id,
            ]);

            $statuses = $this->service->getBudgetStatuses($this->company);
            expect($statuses)->toHaveCount(0);
        });
    });

    describe('checkThresholds', function () {
        it('returns budgets at 80% or above', function () {
            $head1 = AccountHead::factory()->for($this->company)->create();
            $head2 = AccountHead::factory()->for($this->company)->create();
            $head3 = AccountHead::factory()->for($this->company)->create();

            // 85% - should be warning
            Budget::factory()->for($this->company)->create([
                'account_head_id' => $head1->id,
                'year_month' => '2026-03',
                'amount' => 100000,
            ]);
            TransactionAggregate::factory()->create([
                'company_id' => $this->company->id,
                'account_head_id' => $head1->id,
                'year_month' => '2026-03',
                'total_debit' => 85000,
            ]);

            // 110% - should be exceeded
            Budget::factory()->for($this->company)->create([
                'account_head_id' => $head2->id,
                'year_month' => '2026-03',
                'amount' => 100000,
            ]);
            TransactionAggregate::factory()->create([
                'company_id' => $this->company->id,
                'account_head_id' => $head2->id,
                'year_month' => '2026-03',
                'total_debit' => 110000,
            ]);

            // 50% - should NOT be returned
            Budget::factory()->for($this->company)->create([
                'account_head_id' => $head3->id,
                'year_month' => '2026-03',
                'amount' => 100000,
            ]);
            TransactionAggregate::factory()->create([
                'company_id' => $this->company->id,
                'account_head_id' => $head3->id,
                'year_month' => '2026-03',
                'total_debit' => 50000,
            ]);

            $alerts = $this->service->checkThresholds($this->company, '2026-03');
            expect($alerts)->toHaveCount(2);

            $thresholds = $alerts->pluck('threshold')->sort()->values()->toArray();
            expect($thresholds)->toBe([80, 100]);
        });
    });
});
