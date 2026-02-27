<?php

namespace App\Filament\Widgets;

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
        return [
            Stat::make('Unreconciled', Transaction::unreconciled()->count())
                ->icon('heroicon-m-minus-circle')
                ->color('gray'),

            Stat::make('Matched', Transaction::matched()->count())
                ->icon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Flagged', Transaction::flagged()->count())
                ->icon('heroicon-m-flag')
                ->color('danger'),

            Stat::make('Total Matches', ReconciliationMatch::count())
                ->icon('heroicon-o-scale')
                ->color('primary'),
        ];
    }
}
