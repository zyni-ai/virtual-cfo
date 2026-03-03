<?php

namespace App\Filament\Resources\RecurringPatternResource\Pages;

use App\Filament\Resources\RecurringPatternResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRecurringPattern extends EditRecord
{
    protected static string $resource = RecurringPatternResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
