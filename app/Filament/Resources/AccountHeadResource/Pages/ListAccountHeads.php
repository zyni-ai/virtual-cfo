<?php

namespace App\Filament\Resources\AccountHeadResource\Pages;

use App\Filament\Resources\AccountHeadResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAccountHeads extends ListRecords
{
    protected static string $resource = AccountHeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus'),
        ];
    }
}
