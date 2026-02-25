<?php

namespace App\Services\HeadMatcher;

use App\Enums\MappingType;
use App\Models\HeadMapping;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RuleBasedMatcher
{
    /**
     * Match transactions against existing head mapping rules.
     *
     * @param  Collection<int, Transaction>  $transactions
     * @return array<int, array{transaction_id: int, account_head_id: int, mapping_id: int}>
     */
    public function match(Collection $transactions, ?string $bankName = null): array
    {
        $query = HeadMapping::with('accountHead')
            ->whereHas('accountHead', fn (Builder $q) => $q->where('is_active', true));

        if ($bankName) {
            $query->where(function (Builder $q) use ($bankName) {
                $q->whereNull('bank_name')
                    ->orWhere('bank_name', $bankName);
            });
        }

        $rules = $query->get();

        $matches = [];
        /** @var array<int, int> $usageCounts */
        $usageCounts = [];

        foreach ($transactions as $transaction) {
            if ($transaction->mapping_type !== MappingType::Unmapped) {
                continue;
            }

            $description = $transaction->description;

            foreach ($rules as $rule) {
                if ($rule->matches($description)) {
                    $matches[] = [
                        'transaction_id' => $transaction->id,
                        'account_head_id' => $rule->account_head_id,
                        'mapping_id' => $rule->id,
                    ];

                    $usageCounts[$rule->id] = ($usageCounts[$rule->id] ?? 0) + 1;

                    break; // First match wins
                }
            }
        }

        // Bulk update usage counts — one query per matched rule instead of per match
        foreach ($usageCounts as $mappingId => $count) {
            HeadMapping::where('id', $mappingId)->increment('usage_count', $count);
        }

        return $matches;
    }

    /**
     * Apply rule-based matches to transactions.
     */
    public function applyMatches(array $matches): int
    {
        $count = 0;

        foreach ($matches as $match) {
            Transaction::where('id', $match['transaction_id'])
                ->where('mapping_type', MappingType::Unmapped)
                ->update([
                    'account_head_id' => $match['account_head_id'],
                    'mapping_type' => MappingType::Auto,
                ]);

            $count++;
        }

        return $count;
    }
}
