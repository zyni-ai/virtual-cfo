<?php

namespace App\Filament\Widgets;

use App\Models\AccountHead;
use Filament\Widgets\ChartWidget;

class TopAccountHeadsChart extends ChartWidget
{
    protected ?string $heading = 'Top 10 Account Heads by Volume';

    protected static ?int $sort = 5;

    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        $heads = AccountHead::query()
            ->withCount('transactions')
            ->has('transactions')
            ->orderByDesc('transactions_count')
            ->limit(10)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Transactions',
                    'data' => $heads->pluck('transactions_count')->toArray(),
                    'backgroundColor' => '#6366f1',
                ],
            ],
            'labels' => $heads->pluck('name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
