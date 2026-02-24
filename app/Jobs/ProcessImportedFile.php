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

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(
        public ImportedFile $importedFile,
    ) {}

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

            $bankName = $response['bank_name'] ?? null;
            $accountNumber = $response['account_number'] ?? null;
            $transactions = $response['transactions'] ?? [];

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
                'error_message' => 'Statement processing failed. Check application logs for details.',
            ]);
        }
    }
}
