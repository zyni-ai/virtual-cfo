<?php

namespace App\Notifications;

use App\Notifications\Concerns\HasBrandedMail;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MemberRoleChangedNotification extends Notification implements ShouldQueue
{
    use HasBrandedMail, Queueable;

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
        $mail = (new MailMessage)
            ->subject("Role Updated — You're now {$this->newRole} at {$this->companyName}")
            ->line("Your role in **{$this->companyName}** has been updated to **{$this->newRole}**.")
            ->line('Your access permissions have been adjusted to reflect this change. If you have any questions, please reach out to your team admin.')
            ->action('Go to Dashboard', url('/admin'));

        return $this->brandedSalutation($this->brandedGreeting($mail, $notifiable));
    }
}
