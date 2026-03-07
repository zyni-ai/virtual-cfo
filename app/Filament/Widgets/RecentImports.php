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

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent Imports';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ImportedFile::query()
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('original_filename')
                    ->label('File')
                    ->limit(40),

                Tables\Columns\TextColumn::make('status')
                    ->badge(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->since(),
            ])
            ->recordUrl(
                fn (ImportedFile $record): string => ImportedFileResource::getUrl('view', ['record' => $record]),
            )
            ->paginated(false);
    }
}
