<?php

namespace App\Services;

use App\Models\TransactionAggregate;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportingService
{
    /**
     * Return 12 Carbon instances for the Indian financial year (April–March).
     *
     * @return Collection<int, Carbon>
     */
    public function financialYearMonths(?Carbon $ref = null): Collection
    {
        $ref ??= now();

        // FY starts in April: if month < 4, FY started previous calendar year
        $fyStartYear = $ref->month >= 4 ? $ref->year : $ref->year - 1;

        return collect(range(0, 11))->map(
            fn (int $i) => Carbon::create($fyStartYear, 4, 1)->addMonths($i)->startOfMonth()
        );
    }

    /**
     * Build a filtered TransactionAggregate query scoped to current tenant.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<TransactionAggregate>
     */
    public function filteredAggregatesQuery(array $filters = []): Builder
    {
        $query = TransactionAggregate::query()
            ->where('company_id', Filament::getTenant()->id);

        if (empty($filters)) {
            return $query;
        }

        if (! empty($filters['dateFrom'])) {
            $query->where('year_month', '>=', $filters['dateFrom']);
        }

        if (! empty($filters['dateUntil'])) {
            $query->where('year_month', '<=', $filters['dateUntil']);
        }

        if (! empty($filters['bankAccountIds'])) {
            $query->whereIn('bank_account_id', $filters['bankAccountIds']);
        }

        if (! empty($filters['creditCardIds'])) {
            $query->whereIn('credit_card_id', $filters['creditCardIds']);
        }

        if (! empty($filters['accountHeadIds'])) {
            $query->whereIn('account_head_id', $filters['accountHeadIds']);
        }

        return $query;
    }

    /**
     * Base query for head-scoped aggregates with eager-loaded head name.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<TransactionAggregate>
     */
    private function headAggregatesQuery(array $filters = []): Builder
    {
        return $this->filteredAggregatesQuery($filters)
            ->whereNotNull('account_head_id')
            ->with('accountHead:id,name');
    }

    /**
     * Top N heads with monthly debit totals.
     *
     * @param  array<string, mixed>  $filters
     * @return array{heads: array<int, array{name: string, id: int, data: array<string, float>}>, months: array<int, string>}
     */
    public function monthlyTotalsByHead(array $filters = [], int $limit = 10): array
    {
        $baseQuery = $this->headAggregatesQuery($filters);

        // Find top N heads by total debit (no eager-load needed for pluck)
        $topHeadIds = (clone $baseQuery)
            ->without('accountHead')
            ->select('account_head_id')
            ->addSelect(DB::raw('SUM(total_debit) as sum_debit'))
            ->groupBy('account_head_id')
            ->orderByDesc('sum_debit')
            ->limit($limit)
            ->pluck('sum_debit', 'account_head_id');

        if ($topHeadIds->isEmpty()) {
            return ['heads' => [], 'months' => []];
        }

        // Get monthly breakdowns for top heads
        $rows = (clone $baseQuery)
            ->whereIn('account_head_id', $topHeadIds->keys())
            ->select('account_head_id', 'year_month')
            ->addSelect(DB::raw('SUM(total_debit) as sum_debit'))
            ->groupBy('account_head_id', 'year_month')
            ->get();

        $months = $rows->pluck('year_month')->unique()->sort()->values()->all();

        $grouped = $rows->groupBy('account_head_id');

        $heads = $topHeadIds->keys()->map(function (int $headId) use ($grouped) {
            $headRows = $grouped->get($headId, collect());
            $firstRow = $headRows->first();

            /** @var array<string, float> $data */
            $data = [];
            foreach ($headRows as $row) {
                $data[$row->year_month] = round((float) $row->getAttribute('sum_debit'), 2);
            }

            return [
                'id' => $headId,
                'name' => $firstRow?->accountHead?->name ?? 'Unknown',
                'data' => $data,
            ];
        })->values()->all();

        return ['heads' => $heads, 'months' => $months];
    }

    /**
     * Head-by-head comparison between two periods with % change.
     *
     * @param  array<int, string>  $periodA  Array of year_month values
     * @param  array<int, string>  $periodB  Array of year_month values
     * @param  array<string, mixed>  $filters
     * @return array<int, array{head_name: string, head_id: int, period_a_total: float, period_b_total: float, change_percent: float|null}>
     */
    public function periodComparison(array $periodA, array $periodB, array $filters = []): array
    {
        $allMonths = array_merge($periodA, $periodB);

        $rows = $this->headAggregatesQuery($filters)
            ->whereIn('year_month', $allMonths)
            ->select('account_head_id', 'year_month')
            ->addSelect(DB::raw('SUM(total_debit) as sum_debit'))
            ->groupBy('account_head_id', 'year_month')
            ->get();

        /** @var array<int, array{head_name: string, head_id: int, period_a_total: float, period_b_total: float}> $heads */
        $heads = [];
        $periodASet = array_flip($periodA);
        $periodBSet = array_flip($periodB);

        foreach ($rows as $row) {
            $headId = $row->account_head_id;

            if (! isset($heads[$headId])) {
                $heads[$headId] = [
                    'head_id' => $headId,
                    'head_name' => $row->accountHead?->name ?? 'Unknown',
                    'period_a_total' => 0.0,
                    'period_b_total' => 0.0,
                ];
            }

            $amount = round((float) $row->getAttribute('sum_debit'), 2);

            if (isset($periodASet[$row->year_month])) {
                $heads[$headId]['period_a_total'] += $amount;
            }

            if (isset($periodBSet[$row->year_month])) {
                $heads[$headId]['period_b_total'] += $amount;
            }
        }

        // Calculate % change
        return array_values(array_map(function (array $head) {
            $changePercent = null;

            if ($head['period_a_total'] > 0) {
                $changePercent = round(
                    (($head['period_b_total'] - $head['period_a_total']) / $head['period_a_total']) * 100,
                    1
                );
            }

            return array_merge($head, ['change_percent' => $changePercent]);
        }, $heads));
    }

    /**
     * Group totals by account head and source (bank account or credit card).
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array{head_name: string, head_id: int, sources: array<int, array{source_name: string, source_type: string, total_debit: float}>}>
     */
    public function sourceBreakdown(array $filters = []): array
    {
        $rows = $this->headAggregatesQuery($filters)
            ->with(['bankAccount:id,name', 'creditCard:id,name'])
            ->select('account_head_id', 'bank_account_id', 'credit_card_id')
            ->addSelect(DB::raw('SUM(total_debit) as sum_debit'))
            ->groupBy('account_head_id', 'bank_account_id', 'credit_card_id')
            ->get();

        /** @var array<int, array{head_name: string, head_id: int, sources: array<int, array{source_name: string, source_type: string, total_debit: float}>}> $heads */
        $heads = [];

        foreach ($rows as $row) {
            $headId = $row->account_head_id;

            if (! isset($heads[$headId])) {
                $heads[$headId] = [
                    'head_id' => $headId,
                    'head_name' => $row->accountHead?->name ?? 'Unknown',
                    'sources' => [],
                ];
            }

            $sourceName = 'Unknown';
            $sourceType = 'unknown';

            if ($row->bankAccount) {
                $sourceName = $row->bankAccount->name;
                $sourceType = 'bank_account';
            } elseif ($row->creditCard) {
                $sourceName = $row->creditCard->name;
                $sourceType = 'credit_card';
            }

            $heads[$headId]['sources'][] = [
                'source_name' => $sourceName,
                'source_type' => $sourceType,
                'total_debit' => round((float) $row->getAttribute('sum_debit'), 2),
            ];
        }

        return array_values($heads);
    }

    /**
     * Expense summary: pivot table with heads as rows and months as columns.
     *
     * @param  array<string, mixed>  $filters
     * @return array{rows: array<int, array{head_name: string, head_id: int, monthly: array<string, float>, row_total: float, percent_of_total: float}>, months: array<int, string>, grand_total: float}
     */
    public function expenseSummary(array $filters = []): array
    {
        $rows = $this->headAggregatesQuery($filters)
            ->select('account_head_id', 'year_month')
            ->addSelect(DB::raw('SUM(total_debit) as sum_debit'))
            ->groupBy('account_head_id', 'year_month')
            ->get();

        if ($rows->isEmpty()) {
            return ['rows' => [], 'months' => [], 'grand_total' => 0.0];
        }

        $months = $rows->pluck('year_month')->unique()->sort()->values()->all();

        /** @var array<int, array{head_name: string, head_id: int, monthly: array<string, float>, row_total: float}> $headData */
        $headData = [];
        $grandTotal = 0.0;

        foreach ($rows as $row) {
            $headId = $row->account_head_id;
            $amount = round((float) $row->getAttribute('sum_debit'), 2);

            if (! isset($headData[$headId])) {
                $headData[$headId] = [
                    'head_id' => $headId,
                    'head_name' => $row->accountHead?->name ?? 'Unknown',
                    'monthly' => [],
                    'row_total' => 0.0,
                ];
            }

            $headData[$headId]['monthly'][$row->year_month] = $amount;
            $headData[$headId]['row_total'] += $amount;
            $grandTotal += $amount;
        }

        // Round totals and calculate percentages
        $result = [];
        foreach ($headData as $data) {
            $data['row_total'] = round($data['row_total'], 2);
            $data['percent_of_total'] = $grandTotal > 0
                ? round(($data['row_total'] / $grandTotal) * 100, 1)
                : 0.0;
            $result[] = $data;
        }

        // Sort by row_total descending
        usort($result, fn (array $a, array $b) => $b['row_total'] <=> $a['row_total']);

        return [
            'rows' => $result,
            'months' => $months,
            'grand_total' => round($grandTotal, 2),
        ];
    }
}
