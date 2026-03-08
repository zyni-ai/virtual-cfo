<?php

namespace App\Filament\Resources\HeadMappingResource\Pages;

use App\Filament\Resources\HeadMappingResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;

class ListHeadMappings extends ListRecords
{
    protected static string $resource = HeadMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus'),
            Action::make('page_tour')
                ->label('Page Tour')
                ->icon('heroicon-o-academic-cap')
                ->color('gray')
                ->extraAttributes([
                    'x-on:click.prevent' => "Livewire.dispatch('start-tour')",
                ]),
        ];
    }

    public function getFooter(): ?View
    {
        return view('livewire.page-tour-embed', ['pageId' => 'head-mappings']);
    }
}
