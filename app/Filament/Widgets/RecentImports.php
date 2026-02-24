<?php

namespace App\Filament\Widgets;

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
                    ->with('uploader')
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('original_filename')
                    ->label('File')
                    ->limit(40),

                Tables\Columns\TextColumn::make('bank_name')
                    ->label('Bank')
                    ->placeholder('Detecting...'),

                Tables\Columns\TextColumn::make('status')
                    ->badge(),

                Tables\Columns\TextColumn::make('total_rows')
                    ->label('Rows'),

                Tables\Columns\TextColumn::make('mapped_percentage')
                    ->label('Mapped')
                    ->suffix('%'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->since(),
            ])
            ->paginated(false);
    }
}
