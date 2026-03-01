<?php

namespace App\Filament\Pages;

use App\Models\Company;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

class TeamMembers extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Team Members';

    protected static ?string $title = 'Team Members';

    protected static UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.team-members';

    public static function canAccess(): bool
    {
        return auth()->user()->currentRole()?->canManageTeam() ?? false;
    }

    public function table(Table $table): Table
    {
        /** @var Company $company */
        $company = Filament::getTenant();

        return $table
            ->query(
                User::query()
                    ->join('company_user', 'users.id', '=', 'company_user.user_id')
                    ->where('company_user.company_id', $company->id)
                    ->select('users.*', 'company_user.role as pivot_role', 'company_user.created_at as pivot_joined_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('pivot_role')
                    ->label('Role')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                Tables\Columns\TextColumn::make('pivot_joined_at')
                    ->label('Joined')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->defaultSort('name');
    }
}
