<?php

namespace App\Filament\Resources\ImportedFileResource\Pages;

use App\Filament\Resources\ImportedFileResource;
use App\Filament\Widgets\ImportedFileStatsOverview;
use Filament\Resources\Pages\ListRecords;

class ListImportedFiles extends ListRecords
{
    protected static string $resource = ImportedFileResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ImportedFileStatsOverview::class,
        ];
    }
}
