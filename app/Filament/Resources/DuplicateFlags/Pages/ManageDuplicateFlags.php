<?php

namespace App\Filament\Resources\DuplicateFlags\Pages;

use App\Filament\Resources\DuplicateFlags\DuplicateFlagResource;
use Filament\Resources\Pages\ManageRecords;

class ManageDuplicateFlags extends ManageRecords
{
    protected static string $resource = DuplicateFlagResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
