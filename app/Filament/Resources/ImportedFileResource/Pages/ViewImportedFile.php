<?php

namespace App\Filament\Resources\ImportedFileResource\Pages;

use App\Filament\Resources\ImportedFileResource;
use App\Models\ImportedFile;
use Filament\Actions;
use Filament\Infolists;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** @property ImportedFile $record */
class ViewImportedFile extends ViewRecord
{
    protected static string $resource = ImportedFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download')
                ->label('Download PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(fn (): string => route('imported-files.download', $this->record))
                ->openUrlInNewTab(),

            ImportedFileResource::makeSetPasswordAction(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('File Details')
                    ->poll(fn (): ?string => $this->record->isProcessing() ? '10s' : null)
                    ->schema([
                        Infolists\Components\TextEntry::make('original_filename')
                            ->label('Filename'),
                        Infolists\Components\TextEntry::make('bank_name')
                            ->label('Detected Bank')
                            ->placeholder('Not detected'),
                        Infolists\Components\TextEntry::make('statement_period')
                            ->label('Statement Period')
                            ->placeholder('Not detected'),
                        Infolists\Components\TextEntry::make('bankAccount.name')
                            ->label('Bank Account')
                            ->visible(fn (ImportedFile $record): bool => $record->bank_account_id !== null),
                        Infolists\Components\TextEntry::make('creditCard.name')
                            ->label('Credit Card')
                            ->visible(fn (ImportedFile $record): bool => $record->credit_card_id !== null),
                        Infolists\Components\TextEntry::make('statement_type')
                            ->label('Type')
                            ->badge(),
                        Infolists\Components\TextEntry::make('status')
                            ->badge(),
                        Infolists\Components\TextEntry::make('source')
                            ->badge(),
                        Infolists\Components\TextEntry::make('total_rows')
                            ->label('Total Transactions'),
                        Infolists\Components\TextEntry::make('mapped_rows')
                            ->label('Mapped Transactions'),
                        Infolists\Components\TextEntry::make('mapped_percentage')
                            ->label('Mapped %')
                            ->suffix('%'),
                        Infolists\Components\TextEntry::make('uploader.name')
                            ->label('Uploaded By'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Uploaded At')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('processed_at')
                            ->label('Processed At')
                            ->dateTime()
                            ->placeholder('Not processed'),
                        Infolists\Components\TextEntry::make('error_message')
                            ->label('Error')
                            ->visible(fn (ImportedFile $record) => $record->error_message !== null)
                            ->color('danger')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
            ]);
    }
}
