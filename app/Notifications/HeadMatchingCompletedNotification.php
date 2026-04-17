<?php

namespace App\Notifications;

use App\Models\ImportedFile;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class HeadMatchingCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private ?FilamentNotification $notification = null;

    public function __construct(
        public ImportedFile $importedFile,
        public int $ruleMatched,
        public int $aiMatched,
        public int $unmatched,
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
        $total = $this->ruleMatched + $this->aiMatched;

        return $this->notification ??= FilamentNotification::make()
            ->title('Head matching completed')
            ->body("{$this->importedFile->original_filename}: {$total} matched ({$this->ruleMatched} rules, {$this->aiMatched} AI), {$this->unmatched} need review.")
            ->success();
    }
}
