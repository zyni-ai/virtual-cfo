<?php

namespace App\Notifications;

use App\Models\Invitation;
use App\Notifications\Concerns\HasBrandedMail;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvitationAcceptedNotification extends Notification implements ShouldQueue
{
    use HasBrandedMail, Queueable;

    public function __construct(
        public Invitation $invitation,
    ) {
        $this->invitation->loadMissing('company');
    }

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
        $mail = (new MailMessage)
            ->subject("Invitation Accepted — {$this->invitation->email} joined {$this->invitation->company->name}")
            ->line("Great news! **{$this->invitation->email}** has accepted your invitation and joined **{$this->invitation->company->name}** as **{$this->invitation->role->getLabel()}**.")
            ->line('They now have access to the platform based on their assigned role.')
            ->action('View Team', url("/admin/{$this->invitation->company_id}/team-members"));

        return $this->brandedSalutation($this->brandedGreeting($mail, $notifiable));
    }
}
