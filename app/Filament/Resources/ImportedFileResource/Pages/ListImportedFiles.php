<?php

namespace App\Filament\Resources\ImportedFileResource\Pages;

use App\Filament\Resources\ImportedFileResource;
use App\Filament\Widgets\ImportedFileStatsOverview;
use Filament\Resources\Pages\ListRecords;

class ListImportedFiles extends ListRecords
{
    protected static string $resource = ImportedFileResource::class;

    public function getSubheading(): ?string
    {
        return 'Upload and manage bank statements, credit card statements, and invoices';
    }

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
