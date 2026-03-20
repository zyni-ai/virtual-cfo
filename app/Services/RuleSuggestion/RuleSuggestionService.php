<?php

namespace App\Services\RuleSuggestion;

use App\Enums\MappingType;
use App\Models\AccountHead;
use App\Models\Transaction;
use App\Models\User;

class RuleSuggestionService
{
    /**
     * Extract the first meaningful alphabetic keyword from a transaction description.
     * Skips short words and purely numeric tokens.
     */
    public function extractKeyword(string $description): string
    {
        $tokens = preg_split('/[\s\-\/]+/', $description) ?: [$description];

        foreach ($tokens as $token) {
            $clean = preg_replace('/[^a-zA-Z]/', '', $token) ?? '';

            if (strlen($clean) >= 4) {
                return $clean;
            }
        }

        return mb_substr($description, 0, 20);
    }

    /**
     * Count unmapped transactions in the same import whose description contains the keyword.
     * Descriptions are encrypted, so matching is done in PHP after loading.
     * Excludes the source transaction itself.
     */
    public function countSimilarUnmapped(Transaction $transaction, string $keyword): int
    {
        return Transaction::where('imported_file_id', $transaction->imported_file_id)
            ->where('id', '!=', $transaction->id)
            ->where('mapping_type', MappingType::Unmapped)
            ->get()
            ->filter(fn (Transaction $t) => str_contains(strtolower($t->description), strtolower($keyword)))
            ->count();
    }

    /**
     * Return a rule suggestion if there are similar unmapped transactions
     * and the pattern hasn't been dismissed by the user.
     */
    public function suggest(Transaction $transaction, User $user, int $companyId): ?RuleSuggestion
    {
        if ($transaction->account_head_id === null) {
            return null;
        }

        $keyword = $this->extractKeyword($transaction->description);
        $dismissKey = "{$companyId}:{$keyword}";

        $dismissed = $user->dismissed_suggestions ?? [];

        if (in_array($dismissKey, $dismissed)) {
            return null;
        }

        $count = $this->countSimilarUnmapped($transaction, $keyword);

        if ($count === 0) {
            return null;
        }

        $transaction->loadMissing('accountHead');

        /** @var AccountHead|null $accountHead */
        $accountHead = $transaction->accountHead;

        return new RuleSuggestion(
            pattern: $keyword,
            matchCount: $count,
            accountHeadId: $transaction->account_head_id,
            accountHeadName: $accountHead?->name ?? '',
            importedFileId: $transaction->imported_file_id,
        );
    }
}
