<?php

namespace App\Notifications;

use App\Models\ImportedFile;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class HeadMatchingCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

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
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $total = $this->ruleMatched + $this->aiMatched;

        return FilamentNotification::make()
            ->title('Head matching completed')
            ->body("{$this->importedFile->original_filename}: {$total} matched ({$this->ruleMatched} rules, {$this->aiMatched} AI), {$this->unmatched} need review.")
            ->success()
            ->getDatabaseMessage();
    }
}
