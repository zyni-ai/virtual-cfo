<?php

namespace App\Jobs;

use App\Enums\ImportStatus;
use App\Models\ImportedFile;
use App\Services\HeadMatcher\HeadMatcherService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MatchTransactionHeads implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(
        public ImportedFile $importedFile,
    ) {}

    /**
     * Exponential backoff intervals in seconds.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(HeadMatcherService $headMatcherService): void
    {
        try {
            $results = $headMatcherService->matchForFile($this->importedFile);

            Log::info('Head matching completed', [
                'file_id' => $this->importedFile->id,
                'rule_matched' => $results['rule_matched'],
                'ai_matched' => $results['ai_matched'],
                'unmatched' => $results['unmatched'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to match transaction heads', [
                'file_id' => $this->importedFile->id,
                'error' => $e->getMessage(),
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
            'error_message' => 'Head matching permanently failed: '.mb_substr($exception->getMessage(), 0, 500),
        ]);

        Log::error('MatchTransactionHeads permanently failed', [
            'file_id' => $this->importedFile->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
