<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\CreditCardResource\Pages;
use App\Models\Company;
use App\Models\CreditCard;
use BackedEnum;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class CreditCardResource extends Resource
{
    protected static ?string $model = CreditCard::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Credit Cards';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 6;

    protected static bool $isScopedToTenant = false;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Card Name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('card_number')
                            ->label('Card Number')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('pdf_password')
                            ->label('PDF Password')
                            ->password()
                            ->revealable()
                            ->helperText('Used to auto-decrypt password-protected statements from this card.'),

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
                    ->label('Card')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('masked_card_number')
                    ->label('Card Number'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('shared_companies_count')
                    ->label('Shared With')
                    ->counts('sharedCompanies')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                Tables\Columns\TextColumn::make('imported_files_count')
                    ->label('Imports')
                    ->counts('importedFiles')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                TrashedFilter::make(),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Actions\EditAction::make(),

                Actions\Action::make('share_card')
                    ->label('Share')
                    ->icon('heroicon-o-share')
                    ->color('info')
                    ->form([
                        Forms\Components\Select::make('company_ids')
                            ->label('Share with Companies')
                            ->multiple()
                            ->options(function () {
                                $user = Auth::user();
                                $currentTenant = Filament::getTenant();

                                return Company::query()
                                    ->whereHas('users', function (Builder $q) use ($user) {
                                        $q->where('users.id', $user->id)
                                            ->where('company_user.role', UserRole::Admin->value);
                                    })
                                    ->when($currentTenant, fn (Builder $q) => $q->where('companies.id', '!=', $currentTenant->id))
                                    ->pluck('name', 'id');
                            })
                            ->required(),
                    ])
                    ->action(function (CreditCard $record, array $data) {
                        $user = Auth::user();
                        $companyIds = $data['company_ids'];

                        $adminCompanyIds = Company::query()
                            ->whereIn('id', $companyIds)
                            ->whereHas('users', function (Builder $q) use ($user) {
                                $q->where('users.id', $user->id)
                                    ->where('company_user.role', UserRole::Admin->value);
                            })
                            ->pluck('id')
                            ->toArray();

                        if (count($adminCompanyIds) !== count($companyIds)) {
                            Notification::make()
                                ->title('You must be an admin on all target companies')
                                ->danger()
                                ->send();

                            return;
                        }

                        $syncData = [];
                        foreach ($adminCompanyIds as $companyId) {
                            $syncData[$companyId] = ['shared_by' => $user->id];
                        }

                        $record->sharedCompanies()->syncWithoutDetaching($syncData);

                        Notification::make()
                            ->title('Card shared successfully')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([]);
    }

    /** @return Builder<CreditCard> */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        $tenant = Filament::getTenant();

        if ($tenant) {
            $query->visibleToCompany($tenant->getKey());
        }

        return $query;
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCreditCards::route('/'),
            'create' => Pages\CreateCreditCard::route('/create'),
            'edit' => Pages\EditCreditCard::route('/{record}/edit'),
        ];
    }
}
