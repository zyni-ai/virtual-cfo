<?php

namespace App\Notifications;

use App\Models\ImportedFile;
use App\Notifications\Concerns\HasBrandedMail;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ImportFailedNotification extends Notification implements ShouldQueue
{
    use HasBrandedMail, Queueable;

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
            ->body("{$this->importedFile->original_filename} could not be processed: {$this->safeErrorMessage()}")
            ->danger()
            ->getDatabaseMessage();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Import Failed — {$this->importedFile->original_filename}")
            ->line("We weren't able to process **{$this->importedFile->original_filename}**.")
            ->line("**What went wrong:** {$this->safeErrorMessage()}")
            ->line('This usually happens when the file format is unsupported or the statement layout could not be recognized.')
            ->action('Review Imports', url("/admin/{$this->importedFile->company_id}/imported-files"))
            ->line('You can re-upload the file or try a different format (PDF, CSV, or Excel).');

        return $this->brandedSalutation($this->brandedGreeting($mail, $notifiable));
    }

    /**
     * Return a user-safe error message, stripping any raw SQL or database details.
     */
    private function safeErrorMessage(): string
    {
        $message = $this->importedFile->error_message ?? 'An unknown error occurred.';

        if (str_contains($message, 'SQLSTATE') || str_contains($message, 'SQL:')) {
            return 'One or more transactions could not be saved. Please check the file format and try again.';
        }

        return $message;
    }
}
