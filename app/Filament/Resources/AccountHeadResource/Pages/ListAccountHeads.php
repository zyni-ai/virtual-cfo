<?php

namespace App\Filament\Resources\AccountHeadResource\Pages;

use App\Filament\Resources\AccountHeadResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;

class ListAccountHeads extends ListRecords
{
    protected static string $resource = AccountHeadResource::class;

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
        return view('livewire.page-tour-embed', ['pageId' => 'account-heads']);
    }
}
