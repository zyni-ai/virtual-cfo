<?php

namespace App\Notifications;

use App\Models\ImportedFile;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ImportFailedNotification extends Notification implements ShouldQueue
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
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Import failed')
            ->body("{$this->importedFile->original_filename} could not be processed: {$this->importedFile->error_message}")
            ->danger()
            ->getDatabaseMessage();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Import Failed: '.$this->importedFile->original_filename)
            ->line("The file \"{$this->importedFile->original_filename}\" failed to process.")
            ->line("Error: {$this->importedFile->error_message}")
            ->line('Please review the file and try uploading again.');
    }
}
