<?php

namespace App\Filament\Resources\ImportedFileResource\RelationManagers;

use App\Filament\Resources\Concerns\HasTransactionColumns;
use App\Models\Transaction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TransactionsRelationManager extends RelationManager
{
    use HasTransactionColumns;

    protected static string $relationship = 'transactions';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->limit(50)
                    ->tooltip(fn (Transaction $record) => $record->description),

                static::amountColumn(),

                static::currencyColumn(),

                Tables\Columns\TextColumn::make('balance')
                    ->label('Balance')
                    ->numeric(decimalPlaces: 2)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('accountHead.name')
                    ->label('Account Head')
                    ->placeholder('Unmapped')
                    ->searchable()
                    ->description(static::mappingTypeDescription()),
            ])
            ->defaultSort('date', 'desc')
            ->recordActions([
                ViewAction::make(),
            ])
            ->emptyStateHeading('No transactions yet')
            ->emptyStateDescription('Transactions appear here once this file has been processed.')
            ->emptyStateIcon('heroicon-o-banknotes');
    }
}
