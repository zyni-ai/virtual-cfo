<?php

namespace App\Filament\Resources\CreditCardResource\Pages;

use App\Filament\Resources\CreditCardResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCreditCards extends ListRecords
{
    protected static string $resource = CreditCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
