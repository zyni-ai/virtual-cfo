<?php

namespace App\Filament\Pages;

use App\Enums\ImportStatus;
use App\Enums\MatchMethod;
use App\Enums\ReconciliationStatus;
use App\Enums\StatementType;
use App\Jobs\ReconcileImportedFiles;
use App\Models\ImportedFile;
use App\Models\ReconciliationMatch;
use App\Models\Transaction;
use App\Services\Reconciliation\ReconciliationService;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class Reconciliation extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationLabel = 'Reconciliation';

    protected static ?string $title = 'Reconciliation';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.reconciliation';

    /**
     * @return array<Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('run_reconciliation')
                ->label('Run Reconciliation')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->form([
                    Forms\Components\Select::make('bank_file_id')
                        ->label('Bank Statement File')
                        ->options(fn () => ImportedFile::where('statement_type', StatementType::Bank)
                            ->where('status', ImportStatus::Completed)
                            ->pluck('original_filename', 'id'))
                        ->searchable()
                        ->required(),

                    Forms\Components\Select::make('invoice_file_id')
                        ->label('Invoice File')
                        ->options(fn () => ImportedFile::where('statement_type', StatementType::Invoice)
                            ->where('status', ImportStatus::Completed)
                            ->pluck('original_filename', 'id'))
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

            Actions\Action::make('manual_match')
                ->label('Manual Match')
                ->icon('heroicon-o-link')
                ->color('warning')
                ->form([
                    Forms\Components\Select::make('bank_transaction_id')
                        ->label('Bank Transaction')
                        ->options(function () {
                            return Transaction::whereHas('importedFile', fn (Builder $q) => $q->whereIn('statement_type', [StatementType::Bank, StatementType::CreditCard]))
                                ->where(fn (Builder $q) => $q
                                    ->where('reconciliation_status', ReconciliationStatus::Unreconciled)
                                    ->orWhere('reconciliation_status', ReconciliationStatus::Flagged))
                                ->get()
                                ->mapWithKeys(fn (Transaction $t) => [
                                    $t->id => \Illuminate\Support\Carbon::parse($t->date)->format('d M Y').' - '.$t->description.' ('.$t->debit.')',
                                ]);
                        })
                        ->searchable()
                        ->required(),

                    Forms\Components\Select::make('invoice_transaction_id')
                        ->label('Invoice Transaction')
                        ->options(function () {
                            return Transaction::whereHas('importedFile', fn (Builder $q) => $q->where('statement_type', StatementType::Invoice))
                                ->where(fn (Builder $q) => $q
                                    ->where('reconciliation_status', ReconciliationStatus::Unreconciled)
                                    ->orWhere('reconciliation_status', ReconciliationStatus::Flagged))
                                ->get()
                                ->mapWithKeys(fn (Transaction $t) => [
                                    $t->id => \Illuminate\Support\Carbon::parse($t->date)->format('d M Y').' - '.$t->description.' ('.$t->debit.')',
                                ]);
                        })
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data) {
                    $bankTxn = Transaction::findOrFail($data['bank_transaction_id']);
                    $invoiceTxn = Transaction::findOrFail($data['invoice_transaction_id']);

                    ReconciliationMatch::create([
                        'bank_transaction_id' => $bankTxn->id,
                        'invoice_transaction_id' => $invoiceTxn->id,
                        'confidence' => 1.0,
                        'match_method' => MatchMethod::Manual,
                    ]);

                    $bankTxn->update(['reconciliation_status' => ReconciliationStatus::Matched]);
                    $invoiceTxn->update(['reconciliation_status' => ReconciliationStatus::Matched]);

                    // Enrich the bank transaction
                    /** @var \App\Models\ImportedFile $importedFile */
                    $importedFile = $bankTxn->importedFile;
                    (new ReconciliationService)->enrichMatchedTransactions($importedFile);

                    Notification::make()
                        ->title('Manual match created')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ReconciliationMatch::query()->with(['bankTransaction', 'invoiceTransaction']))
            ->columns([
                Tables\Columns\TextColumn::make('bankTransaction.date')
                    ->label('Bank Date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('bankTransaction.description')
                    ->label('Bank Description')
                    ->limit(40)
                    ->tooltip(function (ReconciliationMatch $record): ?string {
                        /** @var Transaction|null $bankTxn */
                        $bankTxn = $record->bankTransaction;

                        return $bankTxn?->description;
                    }),

                Tables\Columns\TextColumn::make('bankTransaction.debit')
                    ->label('Bank Amount')
                    ->numeric(decimalPlaces: 2)
                    ->color('danger'),

                Tables\Columns\TextColumn::make('invoiceTransaction.description')
                    ->label('Invoice')
                    ->limit(40)
                    ->tooltip(function (ReconciliationMatch $record): ?string {
                        /** @var Transaction|null $invoiceTxn */
                        $invoiceTxn = $record->invoiceTransaction;

                        return $invoiceTxn?->description;
                    }),

                Tables\Columns\TextColumn::make('invoiceTransaction.debit')
                    ->label('Invoice Amount')
                    ->numeric(decimalPlaces: 2)
                    ->color('info'),

                Tables\Columns\TextColumn::make('match_method')
                    ->label('Method')
                    ->badge()
                    ->formatStateUsing(fn (MatchMethod $state) => $state->getLabel()),

                Tables\Columns\TextColumn::make('confidence')
                    ->label('Confidence')
                    ->numeric(decimalPlaces: 2)
                    ->color(fn (float $state) => match (true) {
                        $state >= 0.9 => 'success',
                        $state >= 0.7 => 'warning',
                        default => 'danger',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Matched At')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('match_method')
                    ->options(MatchMethod::class),
            ])
            ->actions([
                Actions\Action::make('unmatch')
                    ->label('Unmatch')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (ReconciliationMatch $record) {
                        $record->bankTransaction?->update([
                            'reconciliation_status' => ReconciliationStatus::Unreconciled,
                        ]);
                        $record->invoiceTransaction?->update([
                            'reconciliation_status' => ReconciliationStatus::Unreconciled,
                        ]);
                        $record->delete();

                        Notification::make()
                            ->title('Match removed')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public function getViewData(): array
    {
        return [
            'stats' => [
                'matched' => Transaction::where('reconciliation_status', ReconciliationStatus::Matched)->count(),
                'flagged' => Transaction::where('reconciliation_status', ReconciliationStatus::Flagged)->count(),
                'unreconciled' => Transaction::where('reconciliation_status', ReconciliationStatus::Unreconciled)->count(),
                'total_matches' => ReconciliationMatch::count(),
            ],
        ];
    }
}
