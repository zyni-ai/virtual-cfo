<?php

namespace App\Filament\Resources\ImportedFileResource\Pages;

use App\Filament\Resources\ImportedFileResource;
use App\Filament\Widgets\ImportedFileStatsOverview;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;

class ListImportedFiles extends ListRecords
{
    protected static string $resource = ImportedFileResource::class;

    public function getSubheading(): ?string
    {
        return 'Upload and manage bank statements, credit card statements, and invoices';
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
            ImportedFileStatsOverview::class,
        ];
    }

    public function getFooter(): ?View
    {
        return view('livewire.page-tour-embed', ['pageId' => 'imported-files']);
    }
}
