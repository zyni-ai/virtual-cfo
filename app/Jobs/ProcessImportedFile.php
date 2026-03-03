<?php

namespace App\Jobs;

use App\Enums\ImportStatus;
use App\Enums\StatementType;
use App\Enums\UserRole;
use App\Models\ImportedFile;
use App\Models\User;
use App\Notifications\ImportCompletedNotification;
use App\Notifications\ImportFailedNotification;
use App\Services\DocumentProcessor\DocumentProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ProcessImportedFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(
        public ImportedFile $importedFile,
    ) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            new Middleware\SetTenantForJob,
        ];
    }

    /**
     * Exponential backoff intervals in seconds.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(DocumentProcessor $documentProcessor): void
    {
        try {
            $documentProcessor->process($this->importedFile);

            $this->importedFile->refresh();
            $this->notifySuccess();
            $this->dispatchAutoSuggestions();
        } catch (\Throwable $e) {
            Log::error('Failed to process imported file', [
                'file_id' => $this->importedFile->id,
                'error' => $e->getMessage(),
            ]);

            $this->importedFile->update([
                'status' => ImportStatus::Failed,
                'error_message' => 'Statement processing failed: '.mb_substr($e->getMessage(), 0, 500),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job's permanent failure after all retries are exhausted.
     */
    public function failed(\Throwable $exception): void
    {
        $this->importedFile->update([
            'status' => ImportStatus::Failed,
            'error_message' => 'Processing permanently failed: '.mb_substr($exception->getMessage(), 0, 500),
        ]);

        Log::error('ProcessImportedFile permanently failed', [
            'file_id' => $this->importedFile->id,
            'exception' => $exception->getMessage(),
        ]);

        $this->notifyFailure();
    }

    private function notifySuccess(): void
    {
        if ($this->importedFile->uploaded_by) {
            $this->importedFile->uploader->notify(new ImportCompletedNotification($this->importedFile));
        }
    }

    private function notifyFailure(): void
    {
        $this->importedFile->refresh();
        $notification = new ImportFailedNotification($this->importedFile);

        if ($this->importedFile->uploaded_by) {
            $this->importedFile->uploader->notify($notification);

            return;
        }

        $admins = User::where('role', UserRole::Admin)->get();
        Notification::send($admins, $notification);
    }

    private function dispatchAutoSuggestions(): void
    {
        /** @var StatementType $statementType */
        $statementType = $this->importedFile->statement_type;

        if ($statementType === StatementType::Invoice) {
            SuggestReconciliationMatches::dispatch($this->importedFile);
        }
    }
}
