<?php

namespace App\Notifications;

use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class StatementReceivedByEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $filename,
        public string $companyName,
        public ?string $senderEmail = null,
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
        $body = "Received \"{$this->filename}\" via email for {$this->companyName}.";

        if ($this->senderEmail) {
            $body .= " From: {$this->senderEmail}";
        }

        return FilamentNotification::make()
            ->title('Statement received by email')
            ->body($body)
            ->info()
            ->getDatabaseMessage();
    }
}
