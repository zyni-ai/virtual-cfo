<?php

namespace App\Filament\Widgets;

use App\Enums\MappingType;
use App\Models\Transaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class RecurringAutoMappedWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        $startOfMonth = Carbon::now()->startOfMonth();

        $count = Transaction::whereNotNull('recurring_pattern_id')
            ->where('mapping_type', MappingType::Auto)
            ->where('updated_at', '>=', $startOfMonth)
            ->count();

        return [
            Stat::make('Recurring Auto-Mapped', $count)
                ->description('Transactions auto-mapped this month via recurring patterns')
                ->icon('heroicon-o-arrow-path')
                ->color('info'),
        ];
    }
}
