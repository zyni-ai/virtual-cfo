<?php

namespace App\Filament\Resources\ReconciliationResource\Pages;

use App\Filament\Resources\ReconciliationResource;
use App\Filament\Widgets\ReconciliationStatsOverview;
use Filament\Resources\Pages\ListRecords;

class ListReconciliation extends ListRecords
{
    protected static string $resource = ReconciliationResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            ReconciliationStatsOverview::class,
        ];
    }
}
