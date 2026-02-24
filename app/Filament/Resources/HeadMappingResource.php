<?php

namespace App\Filament\Resources;

use App\Enums\MatchType;
use App\Filament\Resources\HeadMappingResource\Pages;
use App\Models\HeadMapping;
use App\Models\Transaction;
use BackedEnum;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class HeadMappingResource extends Resource
{
    protected static ?string $model = HeadMapping::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $navigationLabel = 'Mapping Rules';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('pattern')
                            ->label('Pattern')
                            ->required()
                            ->helperText('The text pattern to match against transaction descriptions')
                            ->rules([
                                fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get) {
                                    if ($get('match_type') === MatchType::Regex->value && @preg_match($value, '') === false) {
                                        $fail('The pattern is not a valid regular expression.');
                                    }
                                },
                            ]),

                        Forms\Components\Select::make('match_type')
                            ->label('Match Type')
                            ->options(MatchType::class)
                            ->default(MatchType::Contains)
                            ->required(),

                        Forms\Components\Select::make('account_head_id')
                            ->label('Account Head')
                            ->relationship('accountHead', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\TextInput::make('bank_name')
                            ->label('Bank Name')
                            ->helperText('Optional: restrict this rule to a specific bank')
                            ->maxLength(255),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('pattern')
                    ->searchable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('match_type')
                    ->label('Match Type')
                    ->badge(),

                Tables\Columns\TextColumn::make('accountHead.name')
                    ->label('Account Head')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('bank_name')
                    ->label('Bank')
                    ->placeholder('All Banks')
                    ->searchable(),

                Tables\Columns\TextColumn::make('usage_count')
                    ->label('Uses')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Created By'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->defaultSort('usage_count', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('match_type')
                    ->options(MatchType::class),

                Tables\Filters\SelectFilter::make('account_head_id')
                    ->label('Account Head')
                    ->relationship('accountHead', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('test_rule')
                    ->label('Test Rule')
                    ->icon('heroicon-o-beaker')
                    ->color('info')
                    ->action(function (HeadMapping $record) {
                        $matchCount = Transaction::all()
                            ->filter(fn (Transaction $t) => $record->matches($t->description))
                            ->count();

                        Notification::make()
                            ->title("Rule matches {$matchCount} transactions")
                            ->body("Pattern: \"{$record->pattern}\" ({$record->match_type->getLabel()})")
                            ->info()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHeadMappings::route('/'),
            'create' => Pages\CreateHeadMapping::route('/create'),
            'edit' => Pages\EditHeadMapping::route('/{record}/edit'),
        ];
    }
}
