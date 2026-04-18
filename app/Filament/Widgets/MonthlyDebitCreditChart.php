<?php

namespace App\Filament\Widgets;

use App\Models\TransactionAggregate;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MonthlyDebitCreditChart extends ChartWidget
{
    protected ?string $heading = 'Monthly Debit / Credit Totals';

    protected static ?int $sort = 5;

    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        $months = collect(range(11, 0))->map(fn (int $i) => now()->subMonths($i)->startOfMonth());

        $startMonth = $months->first()->format('Y-m');

        /** @var Collection<int, TransactionAggregate> $aggregates */
        $aggregates = TransactionAggregate::query()
            ->where('company_id', Filament::getTenant()->getKey())
            ->where('year_month', '>=', $startMonth)
            ->select('year_month')
            ->addSelect(DB::raw('SUM(total_debit) as sum_debit'))
            ->addSelect(DB::raw('SUM(total_credit) as sum_credit'))
            ->groupBy('year_month')
            ->get()
            ->keyBy('year_month');

        $debitData = [];
        $creditData = [];
        $labels = [];

        foreach ($months as $month) {
            $key = $month->format('Y-m');
            $labels[] = $month->format('M Y');

            $row = $aggregates->get($key);
            $debitData[] = $row ? round((float) $row->getAttribute('sum_debit'), 2) : 0;
            $creditData[] = $row ? round((float) $row->getAttribute('sum_credit'), 2) : 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Debits',
                    'data' => $debitData,
                    'backgroundColor' => '#ef4444',
                ],
                [
                    'label' => 'Credits',
                    'data' => $creditData,
                    'backgroundColor' => '#22c55e',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
