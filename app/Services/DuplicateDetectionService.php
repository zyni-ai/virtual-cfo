<?php

namespace App\Services;

use App\Enums\DuplicateConfidence;
use App\Enums\DuplicateStatus;
use App\Models\DuplicateFlag;
use App\Models\ImportedFile;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DuplicateDetectionService
{
    private const float DESCRIPTION_SIMILARITY_THRESHOLD = 90.0;

    /**
     * Scan for potential duplicate transactions in a newly imported file.
     *
     * @return Collection<int, DuplicateFlag>
     */
    public function scanForDuplicates(ImportedFile $importedFile): Collection
    {
        /** @var Collection<int, Transaction> $newTransactions */
        $newTransactions = $importedFile->transactions()->get();

        if ($newTransactions->isEmpty()) {
            return collect();
        }

        $sourceColumn = $importedFile->bank_account_id ? 'bank_account_id' : 'credit_card_id';
        $sourceId = $importedFile->bank_account_id ?? $importedFile->credit_card_id;

        if ($sourceId === null) {
            return collect();
        }

        $dates = $newTransactions->pluck('date')->map(fn (Carbon $d) => $d->format('Y-m-d'))->unique()->toArray();

        /** @var Collection<int, Transaction> $existingTransactions */
        $existingTransactions = Transaction::query()
            ->where('company_id', $importedFile->company_id)
            ->where('imported_file_id', '!=', $importedFile->id)
            ->whereIn('date', $dates)
            ->whereHas('importedFile', fn (Builder $q) => $q->where($sourceColumn, $sourceId))
            ->get();

        if ($existingTransactions->isEmpty()) {
            return collect();
        }

        /** @var Collection<int, DuplicateFlag> $flags */
        $flags = collect();

        foreach ($newTransactions as $newTxn) {
            $candidates = $existingTransactions->filter(
                fn (Transaction $existing) => Carbon::parse($existing->date)->equalTo(Carbon::parse($newTxn->date))
            );

            foreach ($candidates as $existing) {
                $matchReasons = $this->compareTransactions($newTxn, $existing);

                if (empty($matchReasons)) {
                    continue;
                }

                $confidence = in_array('reference_number', $matchReasons)
                    ? DuplicateConfidence::High
                    : DuplicateConfidence::Medium;

                [$firstId, $secondId] = $this->orderedIds($newTxn->id, $existing->id);

                $flag = DuplicateFlag::firstOrCreate(
                    [
                        'transaction_id' => $firstId,
                        'duplicate_transaction_id' => $secondId,
                    ],
                    [
                        'company_id' => $importedFile->company_id,
                        'confidence' => $confidence,
                        'match_reasons' => $matchReasons,
                        'status' => DuplicateStatus::Pending,
                    ]
                );

                if ($flag->wasRecentlyCreated) {
                    $flags->push($flag);
                }
            }
        }

        return $flags;
    }

    /**
     * Compare two transactions and return match reasons.
     *
     * @return array<int, string>
     */
    private function compareTransactions(Transaction $a, Transaction $b): array
    {
        if (! $this->amountsMatch($a, $b)) {
            return [];
        }

        $reasons = ['amount_date'];

        if ($this->referenceNumbersMatch($a, $b)) {
            $reasons[] = 'reference_number';

            return $reasons;
        }

        if ($this->descriptionsAreSimilar($a, $b)) {
            $reasons[] = 'description_similarity';

            return $reasons;
        }

        return [];
    }

    private function amountsMatch(Transaction $a, Transaction $b): bool
    {
        $aDebit = $a->debit !== null ? (float) $a->debit : null;
        $bDebit = $b->debit !== null ? (float) $b->debit : null;
        $aCredit = $a->credit !== null ? (float) $a->credit : null;
        $bCredit = $b->credit !== null ? (float) $b->credit : null;

        if ($aDebit !== null && $bDebit !== null) {
            return abs($aDebit - $bDebit) < 0.01;
        }

        if ($aCredit !== null && $bCredit !== null) {
            return abs($aCredit - $bCredit) < 0.01;
        }

        return false;
    }

    private function referenceNumbersMatch(Transaction $a, Transaction $b): bool
    {
        if ($a->reference_number === null || $b->reference_number === null) {
            return false;
        }

        return $a->reference_number === $b->reference_number;
    }

    private function descriptionsAreSimilar(Transaction $a, Transaction $b): bool
    {
        /** @var string $descA */
        $descA = $a->description;
        /** @var string $descB */
        $descB = $b->description;

        similar_text(mb_strtolower($descA), mb_strtolower($descB), $percent);

        return $percent >= self::DESCRIPTION_SIMILARITY_THRESHOLD;
    }

    /**
     * Ensure consistent ordering of transaction IDs to prevent duplicate flag pairs.
     *
     * @return array{0: int, 1: int}
     */
    private function orderedIds(int $a, int $b): array
    {
        return $a < $b ? [$a, $b] : [$b, $a];
    }
}
