<?php

namespace App\Notifications;

use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class MemberRemovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $companyName,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Removed from team')
            ->body("You have been removed from {$this->companyName}.")
            ->danger()
            ->getDatabaseMessage();
    }
}
