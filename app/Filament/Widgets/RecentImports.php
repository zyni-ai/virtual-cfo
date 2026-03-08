<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ImportedFileResource;
use App\Models\ImportedFile;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentImports extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = 'Recent Imports';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ImportedFile::query()
                    ->with('uploader')
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('original_filename')
                    ->label('File')
                    ->limit(30)
                    ->tooltip(fn (ImportedFile $record): string => collect([
                        $record->statement_type->getLabel(),
                        $record->bank_name,
                    ])->filter()->implode(' · '))
                    ->description(fn (ImportedFile $record): string => 'Uploaded by '.($record->uploader?->name ?? 'System').' '.$record->created_at->diffForHumans()),

                Tables\Columns\TextColumn::make('status')
                    ->badge(),
            ])
            ->recordUrl(
                fn (ImportedFile $record): string => ImportedFileResource::getUrl('view', ['record' => $record]),
            )
            ->paginated(false);
    }
}
