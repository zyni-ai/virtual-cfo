<?php

namespace App\Filament\Widgets;

use App\Enums\MatchStatus;
use App\Enums\ReconciliationStatus;
use App\Models\ReconciliationMatch;
use App\Models\Transaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ReconciliationStatsOverview extends BaseWidget
{
    protected static bool $isLazy = false;

    protected static bool $isDiscovered = false;

    protected function getStats(): array
    {
        $txnCounts = Transaction::query()
            ->selectRaw('
                COUNT(*) FILTER (WHERE reconciliation_status = ?) AS unreconciled,
                COUNT(*) FILTER (WHERE reconciliation_status = ?) AS matched,
                COUNT(*) FILTER (WHERE reconciliation_status = ?) AS flagged
            ', [
                ReconciliationStatus::Unreconciled->value,
                ReconciliationStatus::Matched->value,
                ReconciliationStatus::Flagged->value,
            ])
            ->first();

        $matchCounts = ReconciliationMatch::query()
            ->selectRaw('
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE status = ?) AS suggested
            ', [MatchStatus::Suggested->value])
            ->first();

        return [
            Stat::make('Unreconciled', $txnCounts->unreconciled ?? 0)
                ->icon('heroicon-m-minus-circle')
                ->color('gray'),

            Stat::make('Matched', $txnCounts->matched ?? 0)
                ->icon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Flagged', $txnCounts->flagged ?? 0)
                ->icon('heroicon-m-flag')
                ->color('danger'),

            Stat::make('Total Matches', $matchCounts->total ?? 0)
                ->icon('heroicon-o-scale')
                ->color('primary'),

            Stat::make('Pending Suggestions', $matchCounts->suggested ?? 0)
                ->icon('heroicon-o-light-bulb')
                ->color('warning'),
        ];
    }
}
