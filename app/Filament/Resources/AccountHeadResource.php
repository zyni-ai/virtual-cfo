<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountHeadResource\Pages;
use App\Models\AccountHead;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AccountHeadResource extends Resource
{
    protected static ?string $model = AccountHead::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Account Heads';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('parent_id')
                            ->label('Parent Head')
                            ->relationship('parent', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('None (Top Level)'),

                        Forms\Components\TextInput::make('group_name')
                            ->label('Group Name')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('tally_guid')
                            ->label('Tally GUID')
                            ->maxLength(255),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Parent')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('group_name')
                    ->label('Group')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('transactions_count')
                    ->label('Transactions')
                    ->counts('transactions')
                    ->sortable(),

                Tables\Columns\TextColumn::make('head_mappings_count')
                    ->label('Rules')
                    ->counts('headMappings')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),

                Tables\Filters\SelectFilter::make('group_name')
                    ->label('Group')
                    ->options(fn () => AccountHead::whereNotNull('group_name')
                        ->distinct()
                        ->pluck('group_name', 'group_name')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('import_tally')
                    ->label('Import from Tally XML')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('info')
                    ->action(function () {
                        // Placeholder: Tally XML import to be implemented
                        // when Tally XML reference file is provided
                    })
                    ->disabled()
                    ->tooltip('Coming soon — awaiting Tally XML reference format'),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccountHeads::route('/'),
            'create' => Pages\CreateAccountHead::route('/create'),
            'edit' => Pages\EditAccountHead::route('/{record}/edit'),
        ];
    }
}
