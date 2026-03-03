<?php

namespace App\Notifications;

use App\Models\ImportedFile;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ImportCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ImportedFile $importedFile,
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
            ->title('Import completed')
            ->body("{$this->importedFile->original_filename} processed successfully — {$this->importedFile->total_rows} transactions found.")
            ->success()
            ->getDatabaseMessage();
    }
}
