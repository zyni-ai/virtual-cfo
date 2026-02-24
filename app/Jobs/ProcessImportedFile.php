<?php

namespace App\Jobs;

use App\Ai\Agents\StatementParser;
use App\Enums\ImportStatus;
use App\Enums\MappingType;
use App\Models\ImportedFile;
use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Files\Document;

class ProcessImportedFile implements ShouldQueue
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

    public function handle(): void
    {
        $this->importedFile->update(['status' => ImportStatus::Processing]);

        try {
            $response = (new StatementParser)->prompt(
                'Parse all transactions from this bank statement. Extract every single transaction row.',
                attachments: [
                    Document::fromStorage($this->importedFile->file_path),
                ]
            );

            if (! isset($response['transactions']) || ! is_array($response['transactions'])) {
                Log::warning('StatementParser returned invalid response', [
                    'file_id' => $this->importedFile->id,
                    'response' => $response,
                ]);

                throw new \RuntimeException(
                    'StatementParser response missing valid transactions array.'
                );
            }

            $bankName = $response['bank_name'] ?? null;
            $accountNumber = $response['account_number'] ?? null;
            $transactions = $response['transactions'];

            if (empty($transactions)) {
                $this->importedFile->update([
                    'status' => ImportStatus::Failed,
                    'error_message' => 'No transactions found in the statement.',
                ]);

                return;
            }

            DB::transaction(function () use ($bankName, $accountNumber, $transactions) {
                if ($bankName) {
                    $this->importedFile->update(['bank_name' => $bankName]);
                }

                if ($accountNumber) {
                    $this->importedFile->update(['account_number' => $accountNumber]);
                }

                foreach ($transactions as $row) {
                    Transaction::create([
                        'imported_file_id' => $this->importedFile->id,
                        'date' => Carbon::parse($row['date']),
                        'description' => $row['description'] ?? '',
                        'reference_number' => $row['reference'] ?? null,
                        'debit' => isset($row['debit']) ? (string) $row['debit'] : null,
                        'credit' => isset($row['credit']) ? (string) $row['credit'] : null,
                        'balance' => isset($row['balance']) ? (string) $row['balance'] : null,
                        'mapping_type' => MappingType::Unmapped,
                        'raw_data' => $row,
                        'bank_format' => $bankName,
                    ]);
                }

                $this->importedFile->update([
                    'status' => ImportStatus::Completed,
                    'total_rows' => count($transactions),
                    'mapped_rows' => 0,
                    'processed_at' => now(),
                ]);
            });

            // Dispatch head matching job
            MatchTransactionHeads::dispatch($this->importedFile);

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
    }
}
