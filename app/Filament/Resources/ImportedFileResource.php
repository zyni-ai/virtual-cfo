<?php

namespace App\Filament\Resources;

use App\Enums\ImportStatus;
use App\Enums\StatementType;
use App\Filament\Resources\ImportedFileResource\Pages;
use App\Jobs\ProcessImportedFile;
use App\Models\ImportedFile;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ImportedFileResource extends Resource
{
    protected static ?string $model = ImportedFile::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-arrow-up';

    protected static ?string $navigationLabel = 'Imported Files';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Upload Statement')
                    ->schema([
                        Forms\Components\FileUpload::make('file_path')
                            ->label('Statement PDF')
                            ->acceptedFileTypes(['application/pdf'])
                            ->directory('statements')
                            ->disk('local')
                            ->maxSize(10240) // 10MB
                            ->required()
                            ->columnSpanFull(),

                        Forms\Components\Select::make('statement_type')
                            ->label('Statement Type')
                            ->options(StatementType::class)
                            ->default(StatementType::Bank)
                            ->required(),

                        Forms\Components\TextInput::make('bank_name')
                            ->label('Bank Name (optional, auto-detected)')
                            ->maxLength(255),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('original_filename')
                    ->label('File')
                    ->searchable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('bank_name')
                    ->label('Bank')
                    ->searchable()
                    ->placeholder('Detecting...'),

                Tables\Columns\TextColumn::make('statement_type')
                    ->label('Type')
                    ->badge(),

                Tables\Columns\TextColumn::make('status')
                    ->badge(),

                Tables\Columns\TextColumn::make('total_rows')
                    ->label('Rows')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('mapped_percentage')
                    ->label('Mapped %')
                    ->suffix('%')
                    ->sortable(query: function ($query, $direction) {
                        $dir = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

                        return $query->orderByRaw(
                            'CASE WHEN total_rows > 0 THEN (mapped_rows::float / total_rows) ELSE 0 END '.$dir
                        );
                    }),

                Tables\Columns\TextColumn::make('uploader.name')
                    ->label('Uploaded By')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(ImportStatus::class),

                Tables\Filters\SelectFilter::make('statement_type')
                    ->options(StatementType::class),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('reprocess')
                    ->label('Re-process')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (ImportedFile $record) {
                        $record->transactions()->delete();
                        $record->update([
                            'status' => ImportStatus::Pending,
                            'total_rows' => 0,
                            'mapped_rows' => 0,
                            'error_message' => null,
                        ]);
                        ProcessImportedFile::dispatch($record);
                    })
                    ->visible(fn (ImportedFile $record) => in_array($record->status, [
                        ImportStatus::Completed,
                        ImportStatus::Failed,
                    ])),

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
            'index' => Pages\ListImportedFiles::route('/'),
            'create' => Pages\CreateImportedFile::route('/create'),
            'view' => Pages\ViewImportedFile::route('/{record}'),
        ];
    }
}
