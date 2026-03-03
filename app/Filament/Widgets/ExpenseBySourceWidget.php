<?php

namespace App\Filament\Widgets;

use App\Models\TransactionAggregate;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ExpenseBySourceWidget extends ChartWidget
{
    protected ?string $heading = 'Expense by Source';

    protected static ?int $sort = 8;

    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        $rows = TransactionAggregate::query()
            ->where('company_id', Filament::getTenant()->id)
            ->whereNotNull('account_head_id')
            ->with(['bankAccount:id,name', 'creditCard:id,name'])
            ->select('bank_account_id', 'credit_card_id')
            ->addSelect(DB::raw('SUM(total_debit) as sum_debit'))
            ->groupBy('bank_account_id', 'credit_card_id')
            ->get();

        if ($rows->isEmpty()) {
            return ['datasets' => [], 'labels' => []];
        }

        $labels = [];
        $data = [];
        $colors = ['#3b82f6', '#ef4444', '#22c55e', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16'];

        foreach ($rows as $index => $row) {
            if ($row->bankAccount) {
                $labels[] = $row->bankAccount->name;
            } elseif ($row->creditCard) {
                $labels[] = $row->creditCard->name;
            } else {
                $labels[] = 'Unknown';
            }

            $data[] = round((float) $row->getAttribute('sum_debit'), 2);
        }

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => array_slice($colors, 0, count($data)),
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
