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

class ReconcileImportedFiles implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(
        public ImportedFile $bankFile,
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

    /**
     * Exponential backoff intervals in seconds.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(ReconciliationService $reconciliationService): void
    {
        try {
            $result = $reconciliationService->reconcile($this->bankFile, $this->invoiceFile);

            Log::info('Reconciliation completed', [
                'bank_file_id' => $this->bankFile->id,
                'invoice_file_id' => $this->invoiceFile->id,
                'matched' => $result->matched,
                'flagged' => $result->flagged,
                'unreconciled' => $result->unreconciled,
            ]);

            // Enrich matched transactions with invoice data
            $reconciliationService->enrichMatchedTransactions($this->bankFile);

            Log::info('Transaction enrichment completed', [
                'bank_file_id' => $this->bankFile->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Reconciliation failed', [
                'bank_file_id' => $this->bankFile->id,
                'invoice_file_id' => $this->invoiceFile->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
