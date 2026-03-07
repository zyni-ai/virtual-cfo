<?php

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Enums\UserRole;
use App\Filament\Widgets\PendingInvitations;
use App\Mail\InvitationMail;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\MemberRemovedNotification;
use App\Notifications\MemberRoleChangedNotification;
use BackedEnum;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use UnitEnum;

class TeamMembers extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Team Members';

    protected static ?string $title = 'Team Members';

    protected static UnitEnum|string|null $navigationGroup = NavigationGroup::Company;

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.team-members';

    /** @return array<class-string> */
    protected function getFooterWidgets(): array
    {
        return [
            PendingInvitations::class,
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()->currentRole()?->canManageTeam() ?? false;
    }

    /** @return array<Actions\Action> */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('invite')
                ->label('Invite Member')
                ->icon('heroicon-o-paper-airplane')
                ->form([
                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->required(),
                    Forms\Components\Select::make('role')
                        ->options(UserRole::class)
                        ->default(UserRole::Viewer->value)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    /** @var Company $company */
                    $company = Filament::getTenant();
                    /** @var User $user */
                    $user = auth()->user();

                    $rateLimitKey = "invitations:{$company->id}";

                    if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
                        Notification::make()
                            ->title('Too many invitations')
                            ->body('You can send a maximum of 10 invitations per hour.')
                            ->danger()
                            ->send();

                        return;
                    }

                    if ($company->users()->where('email', $data['email'])->exists()) {
                        Notification::make()
                            ->title('Already a member')
                            ->body('This user is already a member of this company.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $existing = Invitation::where('company_id', $company->id)
                        ->where('email', $data['email'])
                        ->whereNull('accepted_at')
                        ->first();

                    if ($existing) {
                        $existing->update([
                            'role' => $data['role'],
                            'expires_at' => now()->addDays(7),
                        ]);

                        Mail::to($data['email'])->queue(new InvitationMail($existing->fresh()));

                        RateLimiter::hit($rateLimitKey, 3600);

                        Notification::make()
                            ->title('Invitation resent')
                            ->body("Invitation resent to {$data['email']}.")
                            ->success()
                            ->send();

                        return;
                    }

                    $invitation = Invitation::create([
                        'company_id' => $company->id,
                        'email' => $data['email'],
                        'role' => $data['role'],
                        'token' => Str::random(64),
                        'invited_by' => $user->id,
                        'expires_at' => now()->addDays(7),
                    ]);

                    Mail::to($data['email'])->queue(new InvitationMail($invitation));

                    RateLimiter::hit($rateLimitKey, 3600);

                    Notification::make()
                        ->title('Invitation sent')
                        ->body("Invitation sent to {$data['email']}.")
                        ->success()
                        ->send();
                }),
        ];
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
            ->actions([
                Actions\Action::make('change_role')
                    ->label('Change Role')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (User $record): bool => $record->id !== auth()->id())
                    ->form([
                        Forms\Components\Select::make('role')
                            ->options(UserRole::class)
                            ->default(fn (User $record): string => $record->getAttribute('pivot_role') ?? UserRole::Viewer->value)
                            ->required(),
                    ])
                    ->action(function (array $data, User $record) use ($company): void {
                        /** @var string $oldRole */
                        $oldRole = $record->getAttribute('pivot_role');
                        $newRole = $data['role'] instanceof UserRole ? $data['role']->value : $data['role'];

                        $company->users()->updateExistingPivot($record->id, [
                            'role' => $newRole,
                        ]);

                        activity('team')
                            ->performedOn($company)
                            ->withProperties([
                                'user_id' => $record->id,
                                'email' => $record->email,
                                'old_role' => $oldRole,
                                'new_role' => $newRole,
                            ])
                            ->log('role_changed');

                        $roleLabel = UserRole::tryFrom($newRole)?->getLabel() ?? ucfirst($newRole);

                        $record->notify(new MemberRoleChangedNotification(
                            companyName: $company->name,
                            newRole: $roleLabel,
                        ));

                        Notification::make()
                            ->title('Role updated')
                            ->body("{$record->name}'s role changed to {$roleLabel}.")
                            ->success()
                            ->send();
                    }),

                Actions\Action::make('remove')
                    ->label('Remove')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => $record->id !== auth()->id())
                    ->action(function (User $record) use ($company): void {
                        $company->users()->detach($record->id);

                        activity('team')
                            ->performedOn($company)
                            ->withProperties([
                                'user_id' => $record->id,
                                'email' => $record->email,
                            ])
                            ->log('member_removed');

                        $record->notify(new MemberRemovedNotification(
                            companyName: $company->name,
                        ));

                        Notification::make()
                            ->title('Member removed')
                            ->body("{$record->name} has been removed from the team.")
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('name');
    }
}
