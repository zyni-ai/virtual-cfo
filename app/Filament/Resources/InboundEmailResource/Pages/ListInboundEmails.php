<?php

namespace App\Filament\Resources\InboundEmailResource\Pages;

use App\Filament\Resources\InboundEmailResource;
use Filament\Resources\Pages\ListRecords;

class ListInboundEmails extends ListRecords
{
    protected static string $resource = InboundEmailResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
