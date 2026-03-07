<?php

namespace App\Services;

use App\Enums\PeriodType;
use App\Models\Budget;
use App\Models\Company;
use App\Models\TransactionAggregate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BudgetService
{
    /**
     * Get budget status for all active budgets of a company for the current period.
     *
     * @return Collection<int, array{budget: Budget, actual: float, percentage: float, status: string}>
     */
    public function getBudgetStatuses(Company $company, ?string $yearMonth = null): Collection
    {
        $yearMonth ??= Carbon::now()->format('Y-m');

        $budgets = Budget::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->with('accountHead')
            ->get();

        return $budgets->map(function (Budget $budget) use ($yearMonth) {
            $actual = $this->getActualSpend($budget, $yearMonth);
            $amount = (float) $budget->amount;
            $percentage = $amount > 0 ? ($actual / $amount) * 100 : 0;

            return [
                'budget' => $budget,
                'actual' => $actual,
                'percentage' => round($percentage, 1),
                'status' => $this->getStatus($percentage),
            ];
        });
    }

    /**
     * Get actual spend for a budget based on transaction aggregates.
     */
    public function getActualSpend(Budget $budget, ?string $currentYearMonth = null): float
    {
        $currentYearMonth ??= Carbon::now()->format('Y-m');

        $query = TransactionAggregate::query()
            ->where('company_id', $budget->company_id)
            ->where('account_head_id', $budget->account_head_id);

        match ($budget->period_type) {
            PeriodType::Monthly => $query->where('year_month', $budget->year_month ?? $currentYearMonth),
            PeriodType::Quarterly => $query->whereIn('year_month', $this->quarterMonths($budget->year_month ?? $currentYearMonth)),
            PeriodType::Annual => $query->whereIn('year_month', $this->financialYearMonths($budget->financial_year)),
        };

        return (float) $query->sum('total_debit');
    }

    /**
     * Check budgets and return those that have crossed alert thresholds.
     *
     * @return Collection<int, array{budget: Budget, actual: float, percentage: float, threshold: int}>
     */
    public function checkThresholds(Company $company, ?string $yearMonth = null): Collection
    {
        $statuses = $this->getBudgetStatuses($company, $yearMonth);

        return $statuses->filter(fn (array $s) => $s['percentage'] >= 80)
            ->map(function (array $s) {
                $threshold = $s['percentage'] >= 100 ? 100 : 80;

                return [
                    'budget' => $s['budget'],
                    'actual' => $s['actual'],
                    'percentage' => $s['percentage'],
                    'threshold' => $threshold,
                ];
            })
            ->values();
    }

    private function getStatus(float $percentage): string
    {
        return match (true) {
            $percentage >= 100 => 'exceeded',
            $percentage >= 80 => 'warning',
            default => 'on_track',
        };
    }

    /**
     * Get the 3 months in a quarter from a quarter string like '2026-Q1'.
     *
     * @return array<int, string>
     */
    private function quarterMonths(string $quarterStr): array
    {
        if (preg_match('/^(\d{4})-Q(\d)$/', $quarterStr, $matches)) {
            $year = (int) $matches[1];
            $quarter = (int) $matches[2];
            $startMonth = ($quarter - 1) * 3 + 1;

            return [
                sprintf('%d-%02d', $year, $startMonth),
                sprintf('%d-%02d', $year, $startMonth + 1),
                sprintf('%d-%02d', $year, $startMonth + 2),
            ];
        }

        // Fallback: treat as monthly
        return [$quarterStr];
    }

    /**
     * Get all 12 months for a financial year like '2025-26'.
     *
     * @return array<int, string>
     */
    private function financialYearMonths(string $financialYear): array
    {
        if (! preg_match('/^(\d{4})-(\d{2})$/', $financialYear, $matches)) {
            return [];
        }

        $startYear = (int) $matches[1];
        $months = [];

        // Indian FY: April to March
        for ($m = 4; $m <= 12; $m++) {
            $months[] = sprintf('%d-%02d', $startYear, $m);
        }
        for ($m = 1; $m <= 3; $m++) {
            $months[] = sprintf('%d-%02d', $startYear + 1, $m);
        }

        return $months;
    }
}
