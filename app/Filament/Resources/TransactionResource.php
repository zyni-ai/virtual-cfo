<?php

namespace App\Filament\Resources;

use App\Enums\MappingType;
use App\Enums\MatchMethod;
use App\Enums\MatchType;
use App\Enums\ReconciliationStatus;
use App\Enums\StatementType;
use App\Exports\TransactionCsvExport;
use App\Exports\TransactionExcelExport;
use App\Filament\Resources\TransactionResource\Pages;
use App\Jobs\MatchTransactionHeads;
use App\Models\AccountHead;
use App\Models\HeadMapping;
use App\Models\ImportedFile;
use App\Models\Transaction;
use App\Services\Reconciliation\ReconciliationService;
use App\Services\TallyExport\TallyExportService;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionResource extends Resource
{
    use Concerns\HasTransactionColumns;

    protected static ?string $model = Transaction::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Transactions';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('account_head_id')
                    ->label('Account Head')
                    ->relationship('accountHead', 'name')
                    ->searchable()
                    ->preload(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('importedFile.bank_name')
                    ->label('Bank')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('description')
                    ->limit(50)
                    ->tooltip(fn (Transaction $record) => $record->description),

                Tables\Columns\TextColumn::make('reference_number')
                    ->label('Ref #')
                    ->toggleable(isToggledHiddenByDefault: true),

                static::amountColumn(),

                Tables\Columns\TextColumn::make('balance')
                    ->label('Balance')
                    ->numeric(decimalPlaces: 2)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('accountHead.name')
                    ->label('Account Head')
                    ->placeholder('Unmapped')
                    ->searchable()
                    ->description(static::mappingTypeDescription()),

                Tables\Columns\IconColumn::make('recurring_pattern_id')
                    ->label('Recurring')
                    ->icon(fn ($state) => $state ? 'heroicon-o-arrow-path' : null)
                    ->color('info')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('imported_file_id')
                    ->label('Imported File')
                    ->options(fn () => ImportedFile::pluck('original_filename', 'id'))
                    ->searchable(),

                Tables\Filters\SelectFilter::make('mapping_type')
                    ->options(MappingType::class),

                Tables\Filters\SelectFilter::make('account_head_id')
                    ->label('Account Head')
                    ->relationship('accountHead', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, string $date) => $q->whereDate('date', '>=', $date))
                            ->when($data['until'], fn (Builder $q, string $date) => $q->whereDate('date', '<=', $date));
                    }),

                Tables\Filters\TernaryFilter::make('unmapped_only')
                    ->label('Unmapped Only')
                    ->queries(
                        true: fn (Builder $query) => $query->where('mapping_type', MappingType::Unmapped),
                        false: fn (Builder $query) => $query->where('mapping_type', '!=', MappingType::Unmapped),
                    ),
            ])
            ->actions([
                Actions\Action::make('assign_head')
                    ->label('Assign Head')
                    ->icon('heroicon-o-tag')
                    ->form([
                        Forms\Components\Select::make('account_head_id')
                            ->label('Account Head')
                            ->options(fn () => AccountHead::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Transaction $record, array $data) {
                        DB::transaction(function () use ($record, $data) {
                            $record->update([
                                'account_head_id' => $data['account_head_id'],
                                'mapping_type' => MappingType::Manual,
                                'ai_confidence' => null,
                            ]);

                            $file = $record->importedFile;
                            $file->update([
                                'mapped_rows' => $file->transactions()
                                    ->where('mapping_type', '!=', MappingType::Unmapped)
                                    ->count(),
                            ]);
                        });
                    }),

                Actions\ActionGroup::make([
                    Actions\Action::make('create_rule')
                        ->label('Create Rule')
                        ->icon('heroicon-o-plus-circle')
                        ->color('info')
                        ->form([
                            Forms\Components\TextInput::make('pattern')
                                ->label('Pattern')
                                ->required()
                                ->default(fn (Transaction $record) => $record->description),

                            Forms\Components\Select::make('match_type')
                                ->options(MatchType::class)
                                ->default(MatchType::Contains)
                                ->required(),

                            Forms\Components\Select::make('account_head_id')
                                ->label('Account Head')
                                ->options(fn () => AccountHead::where('is_active', true)->pluck('name', 'id'))
                                ->searchable()
                                ->required()
                                ->default(fn (Transaction $record) => $record->account_head_id),

                            Forms\Components\TextInput::make('bank_name')
                                ->label('Bank Name (optional)')
                                ->default(fn (Transaction $record) => $record->importedFile?->bank_name),
                        ])
                        ->action(function (Transaction $record, array $data) {
                            HeadMapping::create([
                                'pattern' => $data['pattern'],
                                'match_type' => $data['match_type'],
                                'account_head_id' => $data['account_head_id'],
                                'bank_name' => $data['bank_name'] ?: null,
                                'created_by' => Auth::id(),
                            ]);

                            Notification::make()
                                ->title('Mapping rule created')
                                ->success()
                                ->send();
                        })
                        ->visible(fn (Transaction $record) => $record->account_head_id !== null),

                    Actions\Action::make('match_invoice')
                        ->label('Match Invoice')
                        ->icon('heroicon-o-link')
                        ->color('warning')
                        ->form([
                            Forms\Components\CheckboxList::make('invoice_transaction_ids')
                                ->label('Select Invoice(s)')
                                ->options(function (Transaction $record) {
                                    return Transaction::whereHas(
                                        'importedFile',
                                        fn (Builder $q) => $q->where('statement_type', StatementType::Invoice)
                                            ->where('company_id', $record->importedFile?->company_id)
                                    )
                                        ->where('reconciliation_status', ReconciliationStatus::Unreconciled)
                                        ->select(['id', 'description', 'debit'])
                                        ->orderByDesc('date')
                                        ->limit(500)
                                        ->get()
                                        ->mapWithKeys(fn (Transaction $t) => [
                                            $t->id => $t->description.' ('.number_format((float) $t->debit, 2).')',
                                        ]);
                                })
                                ->required(),
                        ])
                        ->action(function (Transaction $record, array $data) {
                            $service = app(ReconciliationService::class);
                            $invoiceTransactions = Transaction::whereIn('id', $data['invoice_transaction_ids'])->get()->keyBy('id');

                            DB::transaction(function () use ($record, $data, $service, $invoiceTransactions) {
                                foreach ($data['invoice_transaction_ids'] as $invoiceId) {
                                    /** @var Transaction $invoiceTxn */
                                    $invoiceTxn = $invoiceTransactions->get($invoiceId);

                                    $service->createMatch($record, $invoiceTxn, 1.0, MatchMethod::Manual);
                                }
                            });

                            $record->loadMissing('importedFile');
                            $service->enrichMatchedTransactions($record->importedFile);

                            Notification::make()
                                ->title('Invoice matched successfully')
                                ->success()
                                ->send();
                        })
                        ->visible(fn (Transaction $record) => $record->reconciliation_status === ReconciliationStatus::Unreconciled
                            && $record->importedFile?->statement_type !== StatementType::Invoice),
                ]),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('bulk_assign_head')
                        ->label('Assign Account Head')
                        ->icon('heroicon-o-tag')
                        ->form([
                            Forms\Components\Select::make('account_head_id')
                                ->label('Account Head')
                                ->options(fn () => AccountHead::where('is_active', true)->pluck('name', 'id'))
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            DB::transaction(function () use ($records, $data) {
                                $records->each(function (Model $record) use ($data) {
                                    $record->update([
                                        'account_head_id' => $data['account_head_id'],
                                        'mapping_type' => MappingType::Manual,
                                        'ai_confidence' => null,
                                    ]);
                                });

                                $fileIds = $records->pluck('imported_file_id')->unique();
                                foreach ($fileIds as $fileId) {
                                    $file = ImportedFile::find($fileId);
                                    if ($file) {
                                        $file->update([
                                            'mapped_rows' => $file->transactions()
                                                ->where('mapping_type', '!=', MappingType::Unmapped)
                                                ->count(),
                                        ]);
                                    }
                                }
                            });
                        })
                        ->deselectRecordsAfterCompletion(),

                    Actions\BulkAction::make('move_to_company')
                        ->label('Move to Company')
                        ->icon('heroicon-o-arrow-right-circle')
                        ->color('warning')
                        ->form([
                            Forms\Components\Select::make('target_company_id')
                                ->label('Target Company')
                                ->options(function () {
                                    $user = Auth::user();
                                    /** @var \App\Models\Company|null $currentTenant */
                                    $currentTenant = \Filament\Facades\Filament::getTenant();

                                    return \App\Models\Company::query()
                                        ->whereHas('users', function (Builder $q) use ($user) {
                                            $q->where('users.id', $user->id)
                                                ->where('company_user.role', \App\Enums\UserRole::Admin->value);
                                        })
                                        ->when($currentTenant, fn (Builder $q) => $q->where('companies.id', '!=', $currentTenant->id))
                                        ->pluck('name', 'id');
                                })
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $targetCompany = \App\Models\Company::find($data['target_company_id']);

                            $creditCardIds = $records->pluck('imported_file_id')
                                ->map(fn ($fileId) => ImportedFile::find($fileId)?->credit_card_id)
                                ->filter()
                                ->unique();

                            $allShared = $creditCardIds->every(function ($cardId) use ($targetCompany) {
                                $card = \App\Models\CreditCard::find($cardId);

                                return $card && $card->isSharedWith($targetCompany);
                            });

                            if (! $allShared) {
                                Notification::make()
                                    ->title('Card must be shared with the target company first')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $count = 0;
                            $records->each(function (Model $record) use ($targetCompany, &$count) {
                                /** @var Transaction $tx */
                                $tx = $record;
                                $tx->moveToCompany($targetCompany);
                                $count++;
                            });

                            Notification::make()
                                ->title($count.' transactions moved to '.$targetCompany->name)
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                Actions\Action::make('run_ai_matching')
                    ->label('Run AI Matching')
                    ->icon('heroicon-o-cpu-chip')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('This will run rule-based and AI matching on all unmapped transactions across all files.')
                    ->action(function () {
                        $files = ImportedFile::whereHas('transactions', function (Builder $q) {
                            $q->where('mapping_type', MappingType::Unmapped);
                        })->get();

                        foreach ($files as $file) {
                            MatchTransactionHeads::dispatch($file);
                        }

                        Notification::make()
                            ->title('AI matching jobs dispatched for '.count($files).' files')
                            ->success()
                            ->send();
                    }),

                Actions\ActionGroup::make([
                    Actions\Action::make('export_tally')
                        ->label('Tally XML')
                        ->icon('heroicon-o-document-text')
                        ->form([
                            Forms\Components\DatePicker::make('from')
                                ->label('From Date'),
                            Forms\Components\DatePicker::make('until')
                                ->label('Until Date'),
                        ])
                        ->action(function (array $data): StreamedResponse {
                            $query = Transaction::whereNotNull('account_head_id')
                                ->with(['accountHead', 'importedFile.company', 'importedFile.bankAccount'])
                                ->orderBy('date');

                            if (! empty($data['from'])) {
                                $query->whereDate('date', '>=', $data['from']);
                            }

                            if (! empty($data['until'])) {
                                $query->whereDate('date', '<=', $data['until']);
                            }

                            $transactions = $query->get();

                            $service = new TallyExportService;
                            $xml = $service->exportTransactions($transactions);

                            return response()->streamDownload(
                                fn () => print ($xml),
                                'tally-export-'.now()->format('Y-m-d-His').'.xml',
                                ['Content-Type' => 'application/xml']
                            );
                        }),

                    Actions\Action::make('export_csv')
                        ->label('CSV')
                        ->icon('heroicon-o-table-cells')
                        ->form([
                            Forms\Components\DatePicker::make('from')
                                ->label('From Date'),
                            Forms\Components\DatePicker::make('until')
                                ->label('Until Date'),
                        ])
                        ->action(function (array $data): BinaryFileResponse {
                            $export = new TransactionCsvExport(
                                from: $data['from'] ?? null,
                                until: $data['until'] ?? null,
                            );

                            return Excel::download(
                                $export,
                                'transactions-'.now()->format('Y-m-d-His').'.csv',
                            );
                        }),

                    Actions\Action::make('export_excel')
                        ->label('Excel')
                        ->icon('heroicon-o-document-arrow-down')
                        ->form([
                            Forms\Components\DatePicker::make('from')
                                ->label('From Date'),
                            Forms\Components\DatePicker::make('until')
                                ->label('Until Date'),
                        ])
                        ->action(function (array $data): BinaryFileResponse {
                            $export = new TransactionExcelExport(
                                from: $data['from'] ?? null,
                                until: $data['until'] ?? null,
                            );

                            return Excel::download(
                                $export,
                                'transactions-'.now()->format('Y-m-d-His').'.xlsx',
                            );
                        }),
                ])
                    ->label('Export')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->button(),
            ])
            ->emptyStateHeading('No transactions yet')
            ->emptyStateDescription('Transactions appear here after you upload and process a bank statement or invoice.')
            ->emptyStateIcon('heroicon-o-banknotes');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
        ];
    }
}
