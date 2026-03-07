<?php

namespace App\Filament\Resources\RecurringPatternResource\Pages;

use App\Filament\Resources\RecurringPatternResource;
use App\Filament\Widgets\RecurringAutoMappedWidget;
use Filament\Resources\Pages\ListRecords;

class ListRecurringPatterns extends ListRecords
{
    protected static string $resource = RecurringPatternResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            RecurringAutoMappedWidget::class,
        ];
    }
}
