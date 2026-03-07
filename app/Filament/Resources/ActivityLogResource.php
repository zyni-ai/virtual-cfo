<?php

namespace App\Filament\Resources;

use App\Enums\NavigationGroup;
use App\Exports\ActivityLogExport;
use App\Filament\Resources\ActivityLogResource\Pages;
use BackedEnum;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Activity Log';

    protected static ?string $slug = 'activity-log';

    protected static ?string $modelLabel = 'Activity';

    protected static ?string $pluralModelLabel = 'Activity Log';

    protected static UnitEnum|string|null $navigationGroup = NavigationGroup::Company;

    protected static ?int $navigationSort = 6;

    protected static bool $isScopedToTenant = false;

    /**
     * Sensitive fields that should be masked in activity log properties.
     *
     * @var array<int, string>
     */
    private const SENSITIVE_FIELDS = [
        'description',
        'debit',
        'credit',
        'balance',
        'account_number',
        'card_number',
        'pdf_password',
        'raw_data',
    ];

    public static function canAccess(): bool
    {
        return auth()->user()->currentRole()?->canManageTeam() ?? false;
    }

    /** @return Builder<Activity> */
    public static function getEloquentQuery(): Builder
    {
        /** @var \App\Models\Company $company */
        $company = Filament::getTenant();
        $tenantUserIds = $company->users()->pluck('users.id');

        /** @var Builder<Activity> */
        return parent::getEloquentQuery()
            ->with('causer')
            ->whereIn('causer_id', $tenantUserIds);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('causer.name')
                    ->label('User')
                    ->placeholder('System'),

                Tables\Columns\TextColumn::make('event')
                    ->label('Event')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Subject')
                    ->formatStateUsing(fn (?string $state): string => $state ? Str::afterLast($state, '\\') : '—'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Action')
                    ->limit(50),

                Tables\Columns\TextColumn::make('properties')
                    ->label('Changes')
                    ->formatStateUsing(fn ($record): string => self::maskProperties($record->properties))
                    ->limit(80)
                    ->tooltip(fn ($record): string => self::maskProperties($record->properties))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('event')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                    ]),

                Tables\Filters\SelectFilter::make('causer_id')
                    ->label('User')
                    ->options(function () {
                        /** @var \App\Models\Company $company */
                        $company = Filament::getTenant();

                        return $company->users()
                            ->pluck('users.name', 'users.id')
                            ->toArray();
                    }),

                Tables\Filters\SelectFilter::make('subject_type')
                    ->label('Subject Type')
                    ->options(function () {
                        /** @var \App\Models\Company $company */
                        $company = Filament::getTenant();
                        $tenantUserIds = $company->users()->pluck('users.id');

                        return Activity::query()
                            ->whereIn('causer_id', $tenantUserIds)
                            ->whereNotNull('subject_type')
                            ->distinct()
                            ->pluck('subject_type', 'subject_type')
                            ->mapWithKeys(fn (string $type) => [$type => Str::afterLast($type, '\\')])
                            ->toArray();
                    }),
            ])
            ->headerActions([
                Actions\Action::make('export')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function () {
                        /** @var \App\Models\Company $company */
                        $company = Filament::getTenant();
                        $tenantUserIds = $company->users()->pluck('users.id');

                        return Excel::download(
                            new ActivityLogExport($tenantUserIds),
                            'activity-log-'.now()->format('Y-m-d').'.csv',
                        );
                    }),
            ]);
    }

    /**
     * Mask sensitive fields in a properties array and return a formatted string.
     *
     * @param  array<string, mixed>|\Illuminate\Support\Collection<string, mixed>|null  $properties
     */
    public static function maskProperties($properties): string
    {
        if ($properties === null) {
            return '—';
        }

        $data = $properties instanceof \Illuminate\Support\Collection
            ? $properties->toArray()
            : $properties;

        if ($data === []) {
            return '—';
        }

        $masked = self::maskNestedProperties($data);

        return collect($masked)
            ->map(fn ($value, $key) => "{$key}: ".(is_array($value) ? json_encode($value) : $value))
            ->implode(', ');
    }

    /**
     * Recursively mask sensitive fields in a properties array.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function maskNestedProperties(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array($key, self::SENSITIVE_FIELDS, true)) {
                $data[$key] = '***';
            } elseif (is_array($value)) {
                $data[$key] = self::maskNestedProperties($value);
            }
        }

        return $data;
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLog::route('/'),
        ];
    }
}
