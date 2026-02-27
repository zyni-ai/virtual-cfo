<?php

namespace App\Filament\Pages;

use App\Enums\ImportStatus;
use App\Enums\MatchMethod;
use App\Enums\ReconciliationStatus;
use App\Enums\StatementType;
use App\Filament\Widgets\ReconciliationStatsOverview;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Reconciliation extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationLabel = 'Reconciliation';

    protected static ?string $title = 'Reconciliation';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.reconciliation';

    /**
     * @return array<class-string>
     */
    public function getHeaderWidgets(): array
    {
        return [
            ReconciliationStatsOverview::class,
        ];
    }

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
                                ->whereIn('reconciliation_status', [ReconciliationStatus::Unreconciled, ReconciliationStatus::Flagged])
                                ->get()
                                ->mapWithKeys(fn (Transaction $t) => [
                                    $t->id => Carbon::parse($t->date)->format('d M Y').' - '.$t->description.' ('.$t->debit.')',
                                ]);
                        })
                        ->searchable()
                        ->required(),

                    Forms\Components\Select::make('invoice_transaction_id')
                        ->label('Invoice Transaction')
                        ->options(function () {
                            return Transaction::whereHas('importedFile', fn (Builder $q) => $q->where('statement_type', StatementType::Invoice))
                                ->whereIn('reconciliation_status', [ReconciliationStatus::Unreconciled, ReconciliationStatus::Flagged])
                                ->get()
                                ->mapWithKeys(fn (Transaction $t) => [
                                    $t->id => Carbon::parse($t->date)->format('d M Y').' - '.$t->description.' ('.$t->debit.')',
                                ]);
                        })
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data) {
                    $bankTxn = Transaction::findOrFail($data['bank_transaction_id']);
                    $invoiceTxn = Transaction::findOrFail($data['invoice_transaction_id']);

                    DB::transaction(function () use ($bankTxn, $invoiceTxn) {
                        ReconciliationMatch::create([
                            'bank_transaction_id' => $bankTxn->id,
                            'invoice_transaction_id' => $invoiceTxn->id,
                            'confidence' => 1.0,
                            'match_method' => MatchMethod::Manual,
                        ]);

                        $bankTxn->update(['reconciliation_status' => ReconciliationStatus::Matched]);
                        $invoiceTxn->update(['reconciliation_status' => ReconciliationStatus::Matched]);

                        /** @var ImportedFile $importedFile */
                        $importedFile = $bankTxn->importedFile;
                        app(ReconciliationService::class)->enrichMatchedTransactions($importedFile);
                    });

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
            ->query(
                Transaction::query()
                    ->with(['importedFile', 'accountHead'])
                    ->whereHas('importedFile', fn (Builder $q) => $q->whereIn('statement_type', [StatementType::Bank, StatementType::CreditCard]))
            )
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(40)
                    ->tooltip(fn (Transaction $record): string => $record->description),

                Tables\Columns\TextColumn::make('debit')
                    ->label('Debit')
                    ->numeric(decimalPlaces: 2)
                    ->color('danger'),

                Tables\Columns\TextColumn::make('credit')
                    ->label('Credit')
                    ->numeric(decimalPlaces: 2)
                    ->color('success'),

                Tables\Columns\TextColumn::make('reconciliation_status')
                    ->label('Status')
                    ->badge(),

                Tables\Columns\TextColumn::make('accountHead.name')
                    ->label('Account Head')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('mapping_type')
                    ->label('Mapping')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('reconciliation_status')
                    ->options(ReconciliationStatus::class),
            ]);
    }
}
