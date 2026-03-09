<?php

namespace App\Filament\Resources\ImportedFileResource\Pages;

use App\Enums\ImportStatus;
use App\Filament\Resources\ImportedFileResource;
use App\Filament\Widgets\ImportedFileStatsOverview;
use App\Models\ImportedFile;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;

class ListImportedFiles extends ListRecords
{
    protected static string $resource = ImportedFileResource::class;

    protected function getTablePollingInterval(): ?string
    {
        return ImportedFile::whereIn('status', [ImportStatus::Pending, ImportStatus::Processing])->exists()
            ? '10s'
            : null;
    }

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
                    'x-on:click.prevent' => "\$dispatch('start-page-tour')",
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
