<?php

namespace App\Filament\Resources\ReconciliationResource\Pages;

use App\Filament\Resources\ReconciliationResource;
use App\Filament\Widgets\ReconciliationStatsOverview;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;

class ListReconciliation extends ListRecords
{
    protected static string $resource = ReconciliationResource::class;

    public function getSubheading(): ?string
    {
        return 'Match bank transactions against invoices';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('page_tour')
                ->label('Page Tour')
                ->icon('heroicon-o-academic-cap')
                ->color('gray')
                ->extraAttributes([
                    'x-on:click.prevent' => "\$dispatch('start-page-tour')",
                ]),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ReconciliationStatsOverview::class,
        ];
    }

    public function getFooter(): ?View
    {
        return view('livewire.page-tour-embed', ['pageId' => 'reconciliation']);
    }
}
