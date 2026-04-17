<?php

namespace App\Notifications;

use App\Models\ImportedFile;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class LowConfidenceMatchesNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ImportedFile $importedFile,
        public int $count,
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
            ->title('Low confidence matches need review')
            ->body("{$this->count} transactions in {$this->importedFile->original_filename} have low confidence matches and need manual review.")
            ->warning()
            ->getDatabaseMessage();
    }
}
