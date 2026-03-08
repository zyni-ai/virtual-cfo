<?php

namespace App\Filament\Resources;

use App\Enums\ImportStatus;
use App\Enums\MatchMethod;
use App\Enums\ReconciliationStatus;
use App\Enums\StatementType;
use App\Filament\Resources\ReconciliationResource\Pages;
use App\Jobs\ReconcileImportedFiles;
use App\Models\ImportedFile;
use App\Models\Transaction;
use App\Services\Reconciliation\ReconciliationService;
use BackedEnum;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ReconciliationResource extends Resource
{
    use Concerns\HasTransactionColumns;

    protected static ?string $model = Transaction::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationLabel = 'Reconciliation';

    protected static ?string $slug = 'reconciliation';

    protected static ?int $navigationSort = 3;

    /** @return Builder<Transaction> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'importedFile',
                'accountHead',
                /** @phpstan-ignore method.notFound */
                'reconciliationMatchesAsBank' => fn (\Illuminate\Database\Eloquent\Relations\Relation $q) => $q->suggested(),
            ])
            ->whereHas('importedFile', fn (Builder $q) => $q->whereIn('statement_type', [StatementType::Bank, StatementType::CreditCard]));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(40)
                    ->tooltip(fn (Transaction $record): string => $record->description)
                    ->searchable(),

                static::amountColumn(),

                Tables\Columns\TextColumn::make('reconciliation_status')
                    ->label('Status')
                    ->badge(),

                Tables\Columns\TextColumn::make('accountHead.name')
                    ->label('Account Head')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable()
                    ->description(static::mappingTypeDescription()),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('reconciliation_status')
                    ->options(ReconciliationStatus::class),
            ])
            ->actions([
                Actions\Action::make('confirm_suggestion')
                    ->label('Confirm')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(function (Transaction $record) {
                        $match = $record->reconciliationMatchesAsBank()->suggested()->firstOrFail();

                        app(ReconciliationService::class)->confirmSuggestion($match);

                        Notification::make()
                            ->title('Suggestion confirmed')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Transaction $record) => $record->reconciliationMatchesAsBank->isNotEmpty()),

                Actions\ActionGroup::make([
                    Actions\Action::make('manual_match')
                        ->label('Match Invoice')
                        ->icon('heroicon-o-link')
                        ->color('warning')
                        ->form([
                            Select::make('invoice_transaction_id')
                                ->label('Invoice Transaction')
                                ->options(function (Transaction $record) {
                                    return Transaction::whereHas('importedFile', fn (Builder $q) => $q->where('statement_type', StatementType::Invoice)
                                        ->where('company_id', $record->importedFile?->company_id))
                                        ->where('reconciliation_status', ReconciliationStatus::Unreconciled)
                                        ->orderByDesc('date')
                                        ->limit(500)
                                        ->get()
                                        ->mapWithKeys(fn (Transaction $t) => [
                                            $t->id => Carbon::parse($t->date)->format('d M Y').' - '.$t->description.' ('.number_format((float) $t->debit, 2).')',
                                        ]);
                                })
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Transaction $record, array $data) {
                            $invoiceTxn = Transaction::findOrFail($data['invoice_transaction_id']);

                            $service = app(ReconciliationService::class);
                            $service->createMatch($record, $invoiceTxn, 1.0, MatchMethod::Manual);
                            $service->enrichMatchedTransactions($record->importedFile);

                            Notification::make()
                                ->title('Invoice matched successfully')
                                ->success()
                                ->send();
                        })
                        ->visible(fn (Transaction $record) => $record->reconciliation_status !== ReconciliationStatus::Matched),

                    Actions\Action::make('reject_suggestions')
                        ->label('Reject All')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Transaction $record) {
                            app(ReconciliationService::class)->rejectAllSuggestions($record);

                            Notification::make()
                                ->title('All suggestions rejected')
                                ->warning()
                                ->send();
                        })
                        ->visible(fn (Transaction $record) => $record->reconciliationMatchesAsBank->isNotEmpty()),
                ]),
            ])
            ->headerActions([
                Actions\Action::make('run_reconciliation')
                    ->label('Run Reconciliation')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->form([
                        Select::make('bank_file_id')
                            ->label('Bank Statement File')
                            ->options(function () {
                                /** @var \App\Models\Company $company */
                                $company = Filament::getTenant();

                                return ImportedFile::where('company_id', $company->id)
                                    ->where('statement_type', StatementType::Bank)
                                    ->where('status', ImportStatus::Completed)
                                    ->pluck('original_filename', 'id');
                            })
                            ->searchable()
                            ->required(),

                        Select::make('invoice_file_id')
                            ->label('Invoice File')
                            ->options(function () {
                                /** @var \App\Models\Company $company */
                                $company = Filament::getTenant();

                                return ImportedFile::where('company_id', $company->id)
                                    ->where('statement_type', StatementType::Invoice)
                                    ->where('status', ImportStatus::Completed)
                                    ->pluck('original_filename', 'id');
                            })
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        /** @var ImportedFile $bankFile */
                        $bankFile = ImportedFile::findOrFail($data['bank_file_id']);
                        /** @var ImportedFile $invoiceFile */
                        $invoiceFile = ImportedFile::findOrFail($data['invoice_file_id']);

                        ReconcileImportedFiles::dispatch($bankFile, $invoiceFile);

                        Notification::make()
                            ->title('Reconciliation job dispatched')
                            ->body("Matching {$bankFile->original_filename} against {$invoiceFile->original_filename}")
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No transactions to reconcile')
            ->emptyStateDescription('Upload bank statements and invoices, then run reconciliation to match them.')
            ->emptyStateIcon('heroicon-o-scale');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReconciliation::route('/'),
        ];
    }
}
