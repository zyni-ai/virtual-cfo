<?php

namespace App\Filament\Widgets;

use App\Models\AccountHead;
use App\Models\TransactionAggregate;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class TopAccountHeadsChart extends ChartWidget
{
    protected ?string $heading = 'Top 10 Account Heads by Amount';

    protected static ?int $sort = 4;

    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        /** @var \Illuminate\Support\Collection<int, TransactionAggregate> $aggregates */
        $aggregates = TransactionAggregate::query()
            ->whereNotNull('account_head_id')
            ->select('account_head_id')
            ->addSelect(DB::raw('SUM(total_debit + total_credit) as total_amount'))
            ->groupBy('account_head_id')
            ->orderByDesc('total_amount')
            ->limit(10)
            ->get();

        $headNames = AccountHead::query()
            ->whereIn('id', $aggregates->pluck('account_head_id'))
            ->pluck('name', 'id');

        return [
            'datasets' => [
                [
                    'label' => 'Total Amount',
                    'data' => $aggregates->map(fn ($row) => round((float) $row->getAttribute('total_amount'), 2))->toArray(),
                    'backgroundColor' => '#6366f1',
                ],
            ],
            'labels' => $aggregates->map(fn ($row) => $headNames->get($row->account_head_id, 'Unknown'))->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
