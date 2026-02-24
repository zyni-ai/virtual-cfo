<?php

namespace App\Filament\Resources\HeadMappingResource\Pages;

use App\Filament\Resources\HeadMappingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHeadMappings extends ListRecords
{
    protected static string $resource = HeadMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
