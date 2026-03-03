<?php

namespace App\Notifications;

use App\Models\Invitation;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvitationAcceptedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Invitation $invitation,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title("{$this->invitation->email} accepted your invitation")
            ->body("They joined {$this->invitation->company->name} as {$this->invitation->role->getLabel()}.")
            ->success()
            ->getDatabaseMessage();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Invitation Accepted')
            ->line("{$this->invitation->email} has accepted your invitation.")
            ->line("They joined {$this->invitation->company->name} as {$this->invitation->role->getLabel()}.");
    }
}
