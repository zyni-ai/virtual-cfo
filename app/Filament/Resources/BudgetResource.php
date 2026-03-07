<?php

namespace App\Filament\Resources;

use App\Enums\PeriodType;
use App\Filament\Resources\BudgetResource\Pages\ManageBudgets;
use App\Models\AccountHead;
use App\Models\Budget;
use BackedEnum;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class BudgetResource extends Resource
{
    protected static ?string $model = Budget::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Budgets';

    protected static ?string $modelLabel = 'Budget';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('account_head_id')
                    ->label('Account Head')
                    ->options(fn () => AccountHead::where('company_id', Filament::getTenant()?->getKey())
                        ->where('is_active', true)
                        ->pluck('name', 'id'))
                    ->required()
                    ->searchable(),

                Select::make('period_type')
                    ->label('Period Type')
                    ->options(PeriodType::class)
                    ->required(),

                TextInput::make('amount')
                    ->label('Budget Amount')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->prefix('₹'),

                TextInput::make('year_month')
                    ->label('Period')
                    ->placeholder('2026-03 or 2026-Q1')
                    ->helperText('Monthly: YYYY-MM, Quarterly: YYYY-QN, Annual: leave blank'),

                TextInput::make('financial_year')
                    ->label('Financial Year')
                    ->required()
                    ->placeholder('2025-26')
                    ->default('2025-26'),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('accountHead.name')
                    ->label('Account Head')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('period_type')
                    ->badge(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Budget')
                    ->numeric(decimalPlaces: 2)
                    ->prefix('₹')
                    ->sortable(),

                Tables\Columns\TextColumn::make('year_month')
                    ->label('Period'),

                Tables\Columns\TextColumn::make('financial_year')
                    ->label('FY'),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('accountHead'))
            ->filters([
                Tables\Filters\SelectFilter::make('period_type')
                    ->options(PeriodType::class),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->default(true),
            ])
            ->recordActions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBudgets::route('/'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        /** @var \App\Models\Company|null $company */
        $company = Filament::getTenant();

        if (! $company) {
            return null;
        }

        $count = static::getModel()::where('company_id', $company->getKey())
            ->where('is_active', true)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }
}
