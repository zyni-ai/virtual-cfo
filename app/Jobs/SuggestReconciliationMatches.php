<?php

namespace App\Jobs;

use App\Models\ImportedFile;
use App\Services\Reconciliation\ReconciliationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SuggestReconciliationMatches implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public ImportedFile $invoiceFile,
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

    public function handle(ReconciliationService $service): void
    {
        $count = $service->suggestMatches($this->invoiceFile);

        Log::info('Reconciliation suggestions created', [
            'invoice_file_id' => $this->invoiceFile->id,
            'suggestions_count' => $count,
        ]);
    }
}
