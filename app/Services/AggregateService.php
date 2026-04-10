<?php

namespace App\Services;

use App\Models\ImportedFile;
use App\Models\Transaction;
use App\Models\TransactionAggregate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AggregateService
{
    /**
     * Full rebuild of aggregates from transactions.
     * Decrypts each transaction's amounts in PHP, groups, and upserts aggregates.
     */
    public function rebuild(?int $companyId = null, ?string $yearMonth = null): void
    {
        $query = Transaction::query()->with('importedFile');

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        if ($yearMonth !== null) {
            $start = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $query->whereBetween('date', [$start, $end]);
        }

        // Delete existing aggregates for the rebuild scope
        $deleteQuery = TransactionAggregate::query();

        if ($companyId !== null) {
            $deleteQuery->where('company_id', $companyId);
        }

        if ($yearMonth !== null) {
            $deleteQuery->where('year_month', $yearMonth);
        }

        $deleteQuery->delete();

        /** @var array<string, array{company_id: int, account_head_id: int|null, bank_account_id: int|null, credit_card_id: int|null, year_month: string, total_debit: float, total_credit: float, transaction_count: int}> $aggregates */
        $aggregates = [];

        $query->chunk(500, function ($transactions) use (&$aggregates) {
            /** @var Collection<int, Transaction> $transactions */
            foreach ($transactions as $transaction) {
                $key = $this->buildAggregateKey($transaction);

                /** @var ImportedFile|null $importedFile */
                $importedFile = $transaction->importedFile;

                if (! isset($aggregates[$key])) {
                    $aggregates[$key] = [
                        'company_id' => $transaction->company_id,
                        'account_head_id' => $transaction->account_head_id,
                        'bank_account_id' => $importedFile?->bank_account_id,
                        'credit_card_id' => $importedFile?->credit_card_id,
                        'year_month' => Carbon::parse($transaction->date)->format('Y-m'),
                        'total_debit' => 0.0,
                        'total_credit' => 0.0,
                        'transaction_count' => 0,
                    ];
                }

                $debit = $transaction->debit !== null ? (float) $transaction->debit : 0;
                $credit = $transaction->credit !== null ? (float) $transaction->credit : 0;

                $aggregates[$key]['total_debit'] = round($aggregates[$key]['total_debit'] + $debit, 2);
                $aggregates[$key]['total_credit'] = round($aggregates[$key]['total_credit'] + $credit, 2);
                $aggregates[$key]['transaction_count']++;
            }
        });

        // Bulk insert aggregates
        $now = now();
        $rows = array_values($aggregates);
        foreach (array_chunk($rows, 100) as $chunk) {
            TransactionAggregate::insert(
                array_map(fn (array $row) => array_merge($row, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]), $chunk)
            );
        }
    }

    /**
     * Rebuild aggregates for all transactions belonging to a specific imported file.
     * Replaces stale null-head aggregates written when transactions were first created.
     */
    public function rebuildForFile(ImportedFile $file): void
    {
        $yearMonths = Transaction::query()
            ->where('imported_file_id', $file->id)
            ->distinct()
            ->pluck(DB::raw("TO_CHAR(date, 'YYYY-MM') AS year_month"));

        if ($yearMonths->isEmpty()) {
            return;
        }

        foreach ($yearMonths as $yearMonth) {
            $this->rebuild($file->company_id, $yearMonth);
        }

        Log::info('Aggregates rebuilt for file', [
            'file_id' => $file->id,
            'year_months' => $yearMonths->all(),
        ]);
    }

    /**
     * Increment aggregate for a newly created transaction.
     */
    public function incrementForTransaction(Transaction $transaction): void
    {
        $transaction->loadMissing('importedFile');

        /** @var ImportedFile|null $importedFile */
        $importedFile = $transaction->importedFile;

        $debit = $transaction->debit !== null ? (float) $transaction->debit : 0;
        $credit = $transaction->credit !== null ? (float) $transaction->credit : 0;

        $this->upsertAggregate(
            companyId: $transaction->company_id,
            accountHeadId: $transaction->account_head_id,
            bankAccountId: $importedFile?->bank_account_id,
            creditCardId: $importedFile?->credit_card_id,
            yearMonth: Carbon::parse($transaction->date)->format('Y-m'),
            debitDelta: $debit,
            creditDelta: $credit,
            countDelta: 1,
        );
    }

    /**
     * Decrement aggregate for a deleted transaction.
     */
    public function decrementForTransaction(Transaction $transaction): void
    {
        $transaction->loadMissing('importedFile');

        /** @var ImportedFile|null $importedFile */
        $importedFile = $transaction->importedFile;

        $debit = $transaction->debit !== null ? (float) $transaction->debit : 0;
        $credit = $transaction->credit !== null ? (float) $transaction->credit : 0;

        $this->upsertAggregate(
            companyId: $transaction->company_id,
            accountHeadId: $transaction->account_head_id,
            bankAccountId: $importedFile?->bank_account_id,
            creditCardId: $importedFile?->credit_card_id,
            yearMonth: Carbon::parse($transaction->date)->format('Y-m'),
            debitDelta: -$debit,
            creditDelta: -$credit,
            countDelta: -1,
        );
    }

    /**
     * Adjust aggregates when a transaction's key fields change.
     * Decrements the old aggregate and increments the new one.
     *
     * @param  array<string, mixed>  $originalAttributes
     */
    public function adjustForUpdate(Transaction $transaction, array $originalAttributes): void
    {
        $trackedFields = ['company_id', 'account_head_id', 'date', 'debit', 'credit'];
        $changed = false;

        foreach ($trackedFields as $field) {
            if (array_key_exists($field, $originalAttributes)) {
                $changed = true;
                break;
            }
        }

        if (! $changed) {
            return;
        }

        $transaction->loadMissing('importedFile');

        /** @var ImportedFile|null $importedFile */
        $importedFile = $transaction->importedFile;

        // Decrement old values
        $oldDebit = array_key_exists('debit', $originalAttributes)
            ? ($originalAttributes['debit'] !== null ? (float) $originalAttributes['debit'] : 0)
            : ($transaction->debit !== null ? (float) $transaction->debit : 0);

        $oldCredit = array_key_exists('credit', $originalAttributes)
            ? ($originalAttributes['credit'] !== null ? (float) $originalAttributes['credit'] : 0)
            : ($transaction->credit !== null ? (float) $transaction->credit : 0);

        $oldCompanyId = $originalAttributes['company_id'] ?? $transaction->company_id;
        $oldAccountHeadId = $originalAttributes['account_head_id'] ?? $transaction->account_head_id;
        $oldDate = array_key_exists('date', $originalAttributes)
            ? Carbon::parse($originalAttributes['date'])->format('Y-m')
            : Carbon::parse($transaction->date)->format('Y-m');

        $this->upsertAggregate(
            companyId: $oldCompanyId,
            accountHeadId: $oldAccountHeadId,
            bankAccountId: $importedFile?->bank_account_id,
            creditCardId: $importedFile?->credit_card_id,
            yearMonth: $oldDate,
            debitDelta: -$oldDebit,
            creditDelta: -$oldCredit,
            countDelta: -1,
        );

        // Increment new values
        $this->incrementForTransaction($transaction);
    }

    /**
     * Upsert an aggregate row using PostgreSQL ON CONFLICT DO UPDATE.
     */
    private function upsertAggregate(
        int $companyId,
        ?int $accountHeadId,
        ?int $bankAccountId,
        ?int $creditCardId,
        string $yearMonth,
        float $debitDelta,
        float $creditDelta,
        int $countDelta,
    ): void {
        $now = now()->toDateTimeString();

        DB::statement('
            INSERT INTO transaction_aggregates
                (company_id, account_head_id, bank_account_id, credit_card_id, year_month, total_debit, total_credit, transaction_count, created_at, updated_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT (
                company_id,
                COALESCE(account_head_id, 0),
                COALESCE(bank_account_id, 0),
                COALESCE(credit_card_id, 0),
                year_month
            )
            DO UPDATE SET
                total_debit = transaction_aggregates.total_debit + EXCLUDED.total_debit,
                total_credit = transaction_aggregates.total_credit + EXCLUDED.total_credit,
                transaction_count = transaction_aggregates.transaction_count + EXCLUDED.transaction_count,
                updated_at = EXCLUDED.updated_at
        ', [
            $companyId,
            $accountHeadId,
            $bankAccountId,
            $creditCardId,
            $yearMonth,
            round($debitDelta, 2),
            round($creditDelta, 2),
            $countDelta,
            $now,
            $now,
        ]);
    }

    /**
     * Build a unique key for grouping transactions into aggregates.
     */
    private function buildAggregateKey(Transaction $transaction): string
    {
        /** @var ImportedFile|null $importedFile */
        $importedFile = $transaction->importedFile;

        $yearMonth = Carbon::parse($transaction->date)->format('Y-m');
        $bankAccountId = $importedFile?->bank_account_id ?? 0;
        $creditCardId = $importedFile?->credit_card_id ?? 0;
        $accountHeadId = $transaction->account_head_id ?? 0;

        return "{$transaction->company_id}:{$accountHeadId}:{$bankAccountId}:{$creditCardId}:{$yearMonth}";
    }
}
