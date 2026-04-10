<?php

namespace App\Jobs;

use App\Enums\MappingType;
use App\Models\ImportedFile;
use App\Notifications\HeadMatchingCompletedNotification;
use App\Notifications\LowConfidenceMatchesNotification;
use App\Services\AggregateService;
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

    public function handle(HeadMatcherService $headMatcherService, AggregateService $aggregateService): void
    {
        try {
            $results = $headMatcherService->matchForFile($this->importedFile);

            Log::info('Head matching completed', [
                'file_id' => $this->importedFile->id,
                'rule_matched' => $results['rule_matched'],
                'ai_matched' => $results['ai_matched'],
                'unmatched' => $results['unmatched'],
            ]);

            $aggregateService->rebuildForFile($this->importedFile);

            $this->importedFile->update(['is_matching' => false]);
            $this->notifyCompletion($results);
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
        $this->importedFile->update(['is_matching' => false]);

        Log::error('MatchTransactionHeads permanently failed', [
            'file_id' => $this->importedFile->id,
            'exception' => $exception->getMessage(),
        ]);
    }

    /**
     * @param  array<string, int>  $results
     */
    private function notifyCompletion(array $results): void
    {
        if (! $this->importedFile->uploaded_by) {
            return;
        }

        $uploader = $this->importedFile->uploader;

        $uploader->notify(new HeadMatchingCompletedNotification(
            $this->importedFile,
            ruleMatched: $results['rule_matched'],
            aiMatched: $results['ai_matched'],
            unmatched: $results['unmatched'],
        ));

        $lowConfidenceCount = $this->importedFile->transactions()
            ->where('mapping_type', MappingType::Ai)
            ->where('ai_confidence', '<', 0.8)
            ->count();

        if ($lowConfidenceCount > 0) {
            $uploader->notify(new LowConfidenceMatchesNotification(
                $this->importedFile,
                count: $lowConfidenceCount,
            ));
        }
    }
}
