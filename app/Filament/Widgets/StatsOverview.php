<?php

namespace App\Filament\Widgets;

use App\Enums\ImportStatus;
use App\Enums\MappingType;
use App\Models\ImportedFile;
use App\Models\Transaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalFiles = ImportedFile::count();
        $totalTransactions = Transaction::count();
        $mappedTransactions = Transaction::where('mapping_type', '!=', MappingType::Unmapped)->count();
        $unmappedTransactions = Transaction::where('mapping_type', MappingType::Unmapped)->count();
        $mappedPercentage = $totalTransactions > 0
            ? round(($mappedTransactions / $totalTransactions) * 100, 1)
            : 0;

        return [
            Stat::make('Total Files', $totalFiles)
                ->description('Imported statements')
                ->icon('heroicon-o-document-arrow-up')
                ->color('primary'),

            Stat::make('Total Transactions', number_format($totalTransactions))
                ->description("{$mappedPercentage}% mapped")
                ->icon('heroicon-o-banknotes')
                ->color('success'),

            Stat::make('Unmapped', number_format($unmappedTransactions))
                ->description('Transactions needing attention')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($unmappedTransactions > 0 ? 'warning' : 'success'),

            Stat::make('Processing', ImportedFile::where('status', ImportStatus::Processing)->count())
                ->description('Files being processed')
                ->icon('heroicon-o-arrow-path')
                ->color('info'),
        ];
    }
}
