<?php

namespace App\Filament\Resources\CreditCardResource\Pages;

use App\Filament\Resources\CreditCardResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateCreditCard extends CreateRecord
{
    protected static string $resource = CreditCardResource::class;

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = Filament::getTenant()?->getKey();

        return $data;
    }
}
