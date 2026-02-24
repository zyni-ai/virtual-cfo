<?php

namespace App\Filament\Resources\ImportedFileResource\Pages;

use App\Filament\Resources\ImportedFileResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListImportedFiles extends ListRecords
{
    protected static string $resource = ImportedFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Upload Statement'),
        ];
    }
}
