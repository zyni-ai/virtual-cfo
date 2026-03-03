<?php

namespace App\Notifications;

use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MemberRoleChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $companyName,
        public string $newRole,
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
            ->title('Your role has been updated')
            ->body("Your role in {$this->companyName} has been changed to {$this->newRole}.")
            ->info()
            ->getDatabaseMessage();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Role Updated')
            ->line("Your role in {$this->companyName} has been changed to {$this->newRole}.");
    }
}
