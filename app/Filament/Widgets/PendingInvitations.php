<?php

namespace App\Filament\Widgets;

use App\Mail\InvitationMail;
use App\Models\Invitation;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Mail;

class PendingInvitations extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Pending Invitations';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Invitation::query()
                    ->with('inviter')
                    ->whereNull('accepted_at')
                    ->where('company_id', Filament::getTenant()->getKey())
                    ->latest()
                    ->limit(25)
            )
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),

                Tables\Columns\TextColumn::make('role')
                    ->badge(),

                Tables\Columns\TextColumn::make('inviter.name')
                    ->label('Invited by'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Sent')
                    ->since(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->since()
                    ->color(fn (Invitation $record): string => $record->isExpired() ? 'danger' : 'gray'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Invitation $record): string => $record->isExpired() ? 'Expired' : 'Pending')
                    ->color(fn (Invitation $record): string => $record->isExpired() ? 'danger' : 'warning'),
            ])
            ->actions([
                Action::make('resend')
                    ->label('Resend')
                    ->icon('heroicon-o-paper-airplane')
                    ->action(function (Invitation $record): void {
                        $record->update(['expires_at' => now()->addDays(7)]);

                        Mail::to($record->email)->queue(new InvitationMail($record));

                        Notification::make()
                            ->title('Invitation resent')
                            ->body("Invitation resent to {$record->email}.")
                            ->success()
                            ->send();
                    }),

                Action::make('revoke')
                    ->label('Revoke')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->hidden(fn (Invitation $record): bool => $record->isExpired())
                    ->action(function (Invitation $record): void {
                        $email = $record->email;
                        $record->delete();

                        Notification::make()
                            ->title('Invitation revoked')
                            ->body("Invitation to {$email} has been revoked.")
                            ->success()
                            ->send();
                    }),
            ])
            ->paginated(false);
    }
}
