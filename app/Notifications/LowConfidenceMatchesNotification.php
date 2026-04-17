<?php

namespace App\Notifications;

use App\Models\ImportedFile;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class LowConfidenceMatchesNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private ?FilamentNotification $notification = null;

    public function __construct(
        public ImportedFile $importedFile,
        public int $count,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->buildFilamentNotification()->getDatabaseMessage();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return $this->buildFilamentNotification()->getBroadcastMessage();
    }

    private function buildFilamentNotification(): FilamentNotification
    {
        return $this->notification ??= FilamentNotification::make()
            ->title('Some matches need your review')
            ->body("{$this->count} transaction(s) in {$this->importedFile->original_filename} have low confidence AI matches. Check the Review Queue.")
            ->persistent()
            ->warning();
    }
}
