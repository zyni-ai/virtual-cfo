<?php

namespace App\Jobs;

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

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(
        public ImportedFile $importedFile,
    ) {}

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
        }
    }
}
