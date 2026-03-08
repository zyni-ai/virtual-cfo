<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use App\Filament\Widgets\TransactionStatsOverview;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;

class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;

    public function getSubheading(): ?string
    {
        return 'Review, map, and export your parsed transactions';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('page_tour')
                ->label('Page Tour')
                ->icon('heroicon-o-academic-cap')
                ->color('gray')
                ->extraAttributes([
                    'x-on:click.prevent' => "Livewire.dispatch('start-tour')",
                ]),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TransactionStatsOverview::class,
        ];
    }

    public function getFooter(): ?View
    {
        return view('livewire.page-tour-embed', ['pageId' => 'transactions']);
    }
}
