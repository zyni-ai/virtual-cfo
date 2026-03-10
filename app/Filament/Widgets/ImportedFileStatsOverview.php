<?php

namespace App\Filament\Widgets;

use App\Enums\ImportStatus;
use App\Models\ImportedFile;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ImportedFileStatsOverview extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected function getPollingInterval(): ?string
    {
        return ImportedFile::activelyProcessing()->exists()
            ? '10s'
            : null;
    }

    protected function getStats(): array
    {
        $totalFiles = ImportedFile::count();
        $processingFiles = ImportedFile::where('status', ImportStatus::Processing)->count();
        $failedFiles = ImportedFile::where('status', ImportStatus::Failed)->count();

        return [
            Stat::make('Total Files', $totalFiles)
                ->description('Imported statements')
                ->icon('heroicon-o-document-arrow-up')
                ->color('primary'),

            Stat::make('Processing', $processingFiles)
                ->description('Files being parsed')
                ->icon('heroicon-o-arrow-path')
                ->color($processingFiles > 0 ? 'info' : 'success'),

            Stat::make('Failed', $failedFiles)
                ->description('Files needing re-upload')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($failedFiles > 0 ? 'danger' : 'success'),
        ];
    }
}
