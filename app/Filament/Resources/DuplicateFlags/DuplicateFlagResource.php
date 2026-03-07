<?php

namespace App\Filament\Resources\DuplicateFlags;

use App\Enums\DuplicateConfidence;
use App\Enums\DuplicateStatus;
use App\Filament\Resources\DuplicateFlags\Pages\ManageDuplicateFlags;
use App\Models\DuplicateFlag;
use BackedEnum;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DuplicateFlagResource extends Resource
{
    protected static ?string $model = DuplicateFlag::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $navigationLabel = 'Duplicate Review';

    protected static ?string $modelLabel = 'Duplicate Flag';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transaction.date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('transaction.description')
                    ->label('Transaction A')
                    ->limit(40)
                    ->tooltip(fn (DuplicateFlag $record) => $record->transaction?->description),

                Tables\Columns\TextColumn::make('duplicateTransaction.description')
                    ->label('Transaction B')
                    ->limit(40)
                    ->tooltip(fn (DuplicateFlag $record) => $record->duplicateTransaction?->description),

                Tables\Columns\TextColumn::make('transaction.debit')
                    ->label('Amount')
                    ->state(fn (DuplicateFlag $record): ?string => $record->transaction?->debit ?? $record->transaction?->credit)
                    ->numeric(decimalPlaces: 2),

                Tables\Columns\TextColumn::make('confidence')
                    ->badge(),

                Tables\Columns\TextColumn::make('match_reasons')
                    ->label('Match Reasons')
                    ->state(fn (DuplicateFlag $record): string => implode(', ', array_map(
                        fn (string $r) => str_replace('_', ' ', $r),
                        $record->match_reasons ?? []
                    ))),

                Tables\Columns\TextColumn::make('status')
                    ->badge(),

                Tables\Columns\TextColumn::make('transaction.importedFile.original_filename')
                    ->label('File A')
                    ->limit(20)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('duplicateTransaction.importedFile.original_filename')
                    ->label('File B')
                    ->limit(20)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'transaction.importedFile',
                'duplicateTransaction.importedFile',
            ]))
            ->filters([
                Tables\Filters\SelectFilter::make('confidence')
                    ->options(DuplicateConfidence::class),

                Tables\Filters\SelectFilter::make('status')
                    ->options(DuplicateStatus::class)
                    ->default(DuplicateStatus::Pending->value),
            ])
            ->recordActions([
                Actions\Action::make('confirm_duplicate')
                    ->label('Confirm')
                    ->icon('heroicon-o-check-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('This will soft-delete the duplicate transaction and link it to the original.')
                    ->action(function (DuplicateFlag $record) {
                        DB::transaction(function () use ($record) {
                            $record->update([
                                'status' => DuplicateStatus::Confirmed,
                                'resolved_by' => Auth::id(),
                                'resolved_at' => now(),
                            ]);

                            $duplicate = $record->duplicateTransaction;
                            $duplicate->update(['duplicate_of_id' => $record->transaction_id]);
                            $duplicate->delete();
                        });
                    })
                    ->visible(fn (DuplicateFlag $record) => $record->status === DuplicateStatus::Pending),

                Actions\Action::make('dismiss')
                    ->label('Dismiss')
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Mark as not a duplicate. These transactions will not be flagged again.')
                    ->action(function (DuplicateFlag $record) {
                        $record->update([
                            'status' => DuplicateStatus::Dismissed,
                            'resolved_by' => Auth::id(),
                            'resolved_at' => now(),
                        ]);
                    })
                    ->visible(fn (DuplicateFlag $record) => $record->status === DuplicateStatus::Pending),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('bulk_confirm')
                        ->label('Confirm as Duplicates')
                        ->icon('heroicon-o-check-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            DB::transaction(function () use ($records) {
                                /** @var Collection<int, DuplicateFlag> $records */
                                $records->each(function (DuplicateFlag $record) {
                                    if ($record->status !== DuplicateStatus::Pending) {
                                        return;
                                    }

                                    $record->update([
                                        'status' => DuplicateStatus::Confirmed,
                                        'resolved_by' => Auth::id(),
                                        'resolved_at' => now(),
                                    ]);

                                    $duplicate = $record->duplicateTransaction;
                                    $duplicate->update(['duplicate_of_id' => $record->transaction_id]);
                                    $duplicate->delete();
                                });
                            });
                        })
                        ->deselectRecordsAfterCompletion(),

                    Actions\BulkAction::make('bulk_dismiss')
                        ->label('Dismiss All')
                        ->icon('heroicon-o-x-circle')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            /** @var Collection<int, DuplicateFlag> $records */
                            $records->each(function (DuplicateFlag $record) {
                                if ($record->status !== DuplicateStatus::Pending) {
                                    return;
                                }

                                $record->update([
                                    'status' => DuplicateStatus::Dismissed,
                                    'resolved_by' => Auth::id(),
                                    'resolved_at' => now(),
                                ]);
                            });
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDuplicateFlags::route('/'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', DuplicateStatus::Pending)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
