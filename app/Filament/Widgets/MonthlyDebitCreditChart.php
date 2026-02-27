<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class MonthlyDebitCreditChart extends ChartWidget
{
    protected ?string $heading = 'Monthly Debit / Credit Totals';

    protected static ?int $sort = 6;

    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        $months = collect(range(11, 0))->map(fn (int $i) => now()->subMonths($i)->startOfMonth());

        $startDate = $months->first();

        // Load all transactions from the last 12 months via Eloquent
        // (debit/credit are encrypted -- cannot SUM in SQL)
        $transactions = Transaction::query()
            ->where('date', '>=', $startDate)
            ->get();

        $grouped = $transactions->groupBy(fn (Transaction $t) => Carbon::parse($t->date)->format('Y-m'));

        $debitData = [];
        $creditData = [];
        $labels = [];

        foreach ($months as $month) {
            $key = $month->format('Y-m');
            $labels[] = $month->format('M Y');

            $monthTransactions = $grouped->get($key, collect());

            $debitData[] = round(
                $monthTransactions->sum(fn (Transaction $t) => $t->debit !== null ? (float) $t->debit : 0),
                2,
            );

            $creditData[] = round(
                $monthTransactions->sum(fn (Transaction $t) => $t->credit !== null ? (float) $t->credit : 0),
                2,
            );
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
