<?php

namespace App\Filament\Resources;

use App\Enums\AccountType;
use App\Enums\NavigationGroup;
use App\Filament\Resources\BankAccountResource\Pages;
use App\Models\BankAccount;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class BankAccountResource extends Resource
{
    protected static ?string $model = BankAccount::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationLabel = 'Bank Accounts';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Company;

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Bank Name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('e.g., HDFC Bank, ICICI Bank, State Bank of India'),

                        Forms\Components\TextInput::make('account_number')
                            ->label('Account Number')
                            ->maxLength(255)
                            ->helperText('Stored securely. Used to auto-detect the account when parsing statements.'),

                        Forms\Components\TextInput::make('ifsc_code')
                            ->label('IFSC Code')
                            ->maxLength(255)
                            ->helperText('The 11-character IFSC code for this branch (e.g., HDFC0001234).'),

                        Forms\Components\TextInput::make('branch')
                            ->label('Branch')
                            ->maxLength(255)
                            ->helperText('Branch name for your reference.'),

                        Forms\Components\Select::make('account_type')
                            ->label('Account Type')
                            ->options(AccountType::class)
                            ->default(AccountType::Current)
                            ->required()
                            ->helperText('Select the type of bank account.'),

                        Forms\Components\TextInput::make('pdf_password')
                            ->label('PDF Password')
                            ->password()
                            ->revealable()
                            ->helperText('Used to auto-decrypt password-protected statements from this account.'),

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
                    ->label('Bank')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('masked_account_number')
                    ->label('Account Number'),

                Tables\Columns\TextColumn::make('account_type')
                    ->label('Type')
                    ->badge(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

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

                Tables\Filters\SelectFilter::make('account_type')
                    ->options(AccountType::class),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
                Actions\ForceDeleteAction::make(),
                Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                    Actions\ForceDeleteBulkAction::make(),
                    Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No bank accounts yet')
            ->emptyStateDescription('Add your bank accounts to start uploading and processing statements.')
            ->emptyStateIcon('heroicon-o-building-library');
    }

    /** @return Builder<BankAccount> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBankAccounts::route('/'),
            'create' => Pages\CreateBankAccount::route('/create'),
            'edit' => Pages\EditBankAccount::route('/{record}/edit'),
        ];
    }
}
