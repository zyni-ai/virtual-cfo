<?php

namespace App\Filament\Widgets;

use App\Services\ReportingService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TopMoversWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $service = app(ReportingService::class);

        $currentMonth = [now()->format('Y-m')];
        $previousMonth = [now()->subMonth()->format('Y-m')];

        $comparison = $service->periodComparison($previousMonth, $currentMonth);

        if (empty($comparison)) {
            return [
                Stat::make('Top Movers', 'No data')
                    ->description('No expense data available yet')
                    ->color('gray'),
            ];
        }

        usort($comparison, fn (array $a, array $b) => abs($b['change_percent'] ?? 0) <=> abs($a['change_percent'] ?? 0));

        $topMovers = array_slice($comparison, 0, 3);

        return array_map(function (array $mover) {
            $change = $mover['change_percent'];
            $isIncrease = $change !== null && $change > 0;
            $isDecrease = $change !== null && $change < 0;

            $description = match (true) {
                $change === null => 'New this month',
                $isIncrease => number_format(abs($change), 1).'% increase',
                $isDecrease => number_format(abs($change), 1).'% decrease',
                default => 'No change',
            };

            $icon = match (true) {
                $isIncrease => 'heroicon-m-arrow-trending-up',
                $isDecrease => 'heroicon-m-arrow-trending-down',
                default => 'heroicon-m-minus',
            };

            $color = match (true) {
                $isIncrease => 'danger',
                $isDecrease => 'success',
                default => 'gray',
            };

            return Stat::make($mover['head_name'], number_format($mover['period_b_total'], 0))
                ->description($description)
                ->descriptionIcon($icon)
                ->color($color);
        }, $topMovers);
    }
}
