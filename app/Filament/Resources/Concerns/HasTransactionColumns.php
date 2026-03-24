<?php

namespace App\Filament\Resources\Concerns;

use App\Models\Transaction;
use Filament\Tables;

trait HasTransactionColumns
{
    protected static function amountColumn(): Tables\Columns\TextColumn
    {
        return Tables\Columns\TextColumn::make('amount')
            ->label('Amount')
            ->state(fn (Transaction $record): ?string => $record->debit ?? $record->credit)
            ->numeric(decimalPlaces: 2)
            ->color(fn (Transaction $record): string => $record->debit ? 'danger' : 'success')
            ->icon(fn (Transaction $record): string => $record->debit ? 'heroicon-m-arrow-up' : 'heroicon-m-arrow-down')
            ->placeholder('-');
    }

    protected static function currencyColumn(): Tables\Columns\TextColumn
    {
        return Tables\Columns\TextColumn::make('currency')
            ->label('Currency')
            ->badge()
            ->color('info')
            ->toggleable(isToggledHiddenByDefault: true)
            ->placeholder('-');
    }

    protected static function mappingTypeDescription(): \Closure
    {
        return fn (Transaction $record): ?string => $record->mapping_type->getDescription($record->ai_confidence);
    }
}
