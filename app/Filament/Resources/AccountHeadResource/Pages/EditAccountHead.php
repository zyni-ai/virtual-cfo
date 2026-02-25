<?php

namespace App\Filament\Resources\AccountHeadResource\Pages;

use App\Filament\Resources\AccountHeadResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAccountHead extends EditRecord
{
    protected static string $resource = AccountHeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
