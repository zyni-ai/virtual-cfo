<?php

namespace App\Filament\Resources;

use App\Enums\NavigationGroup;
use App\Filament\Resources\RecurringPatternResource\Pages;
use App\Models\RecurringPattern;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class RecurringPatternResource extends Resource
{
    protected static ?string $model = RecurringPattern::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationLabel = 'Recurring Patterns';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Configuration;

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('description_pattern')
                            ->label('Description Pattern')
                            ->disabled(),

                        Forms\Components\Select::make('account_head_id')
                            ->label('Account Head')
                            ->relationship('accountHead', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('None'),

                        Forms\Components\Select::make('frequency')
                            ->options([
                                'monthly' => 'Monthly',
                                'quarterly' => 'Quarterly',
                                'annual' => 'Annual',
                                'irregular' => 'Irregular',
                            ]),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('description_pattern')
                    ->label('Pattern')
                    ->searchable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('accountHead.name')
                    ->label('Account Head')
                    ->placeholder('Unassigned')
                    ->searchable(),

                Tables\Columns\TextColumn::make('frequency')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'monthly' => 'success',
                        'quarterly' => 'info',
                        'annual' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('occurrence_count')
                    ->label('Occurrences')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('avg_amount')
                    ->label('Avg Amount')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_seen_at')
                    ->label('Last Seen')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->defaultSort('occurrence_count', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),

                Tables\Filters\SelectFilter::make('frequency')
                    ->options([
                        'monthly' => 'Monthly',
                        'quarterly' => 'Quarterly',
                        'annual' => 'Annual',
                        'irregular' => 'Irregular',
                    ]),

                Tables\Filters\SelectFilter::make('account_head_id')
                    ->label('Account Head')
                    ->relationship('accountHead', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No recurring patterns yet')
            ->emptyStateDescription('Recurring patterns are auto-detected from your transactions over time. They help predict and auto-map future entries.')
            ->emptyStateIcon('heroicon-o-arrow-path');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRecurringPatterns::route('/'),
            'edit' => Pages\EditRecurringPattern::route('/{record}/edit'),
        ];
    }
}
