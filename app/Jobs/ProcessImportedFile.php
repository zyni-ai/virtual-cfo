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
use App\Services\DuplicateDetectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
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
            $this->scanForDuplicates();
        } catch (\Throwable $e) {
            Log::error('Failed to process imported file', [
                'file_id' => $this->importedFile->id,
                'error' => $e->getMessage(),
            ]);

            $this->importedFile->update([
                'status' => ImportStatus::Failed,
                'error_message' => $this->sanitiseErrorMessage($e, 'Statement processing failed'),
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
            'error_message' => $this->sanitiseErrorMessage($exception, 'Processing permanently failed'),
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

            return;
        }

        if ($this->importedFile->source->shouldAutoMatchHeads()) {
            MatchTransactionHeads::dispatch($this->importedFile);
        }
    }

    private function sanitiseErrorMessage(\Throwable $exception, string $prefix): string
    {
        if ($exception instanceof QueryException || $exception instanceof \PDOException) {
            return "{$prefix}: one or more transactions could not be saved. Please check the file format and try again.";
        }

        return "{$prefix}: a processing error occurred. Please try again or contact support.";
    }

    private function scanForDuplicates(): void
    {
        try {
            $service = app(DuplicateDetectionService::class);
            $flags = $service->scanForDuplicates($this->importedFile);

            if ($flags->isNotEmpty()) {
                Log::info('Duplicate detection found potential duplicates', [
                    'file_id' => $this->importedFile->id,
                    'count' => $flags->count(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Duplicate detection failed (non-fatal)', [
                'file_id' => $this->importedFile->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
