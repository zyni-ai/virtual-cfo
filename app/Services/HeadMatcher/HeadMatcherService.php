<?php

namespace App\Services\HeadMatcher;

use App\Ai\Agents\HeadMatcher;
use App\Enums\MappingType;
use App\Models\AccountHead;
use App\Models\ImportedFile;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Collection;

class HeadMatcherService
{
    protected float $confidenceThreshold = 0.8;

    public function __construct(
        protected RuleBasedMatcher $ruleBasedMatcher,
    ) {}

    public function setConfidenceThreshold(float $threshold): static
    {
        $this->confidenceThreshold = $threshold;

        return $this;
    }

    /**
     * Run the full matching pipeline: rules first, then AI for remaining.
     */
    public function matchForFile(ImportedFile $importedFile): array
    {
        $transactions = $importedFile->transactions()
            ->where('mapping_type', MappingType::Unmapped)
            ->get();

        if ($transactions->isEmpty()) {
            return ['rule_matched' => 0, 'ai_matched' => 0, 'unmatched' => 0];
        }

        // Pass 1: Rule-based matching
        $ruleMatches = $this->ruleBasedMatcher->match($transactions, $importedFile->bank_name);
        $ruleCount = $this->ruleBasedMatcher->applyMatches($ruleMatches);

        // Refresh for remaining unmapped
        $unmapped = $importedFile->transactions()
            ->where('mapping_type', MappingType::Unmapped)
            ->get();

        $aiCount = 0;

        if ($unmapped->isNotEmpty()) {
            // Pass 2: AI matching
            $aiCount = $this->runAiMatching($unmapped);
        }

        // Update file stats
        $importedFile->update([
            'mapped_rows' => $importedFile->transactions()
                ->where('mapping_type', '!=', MappingType::Unmapped)
                ->count(),
        ]);

        return [
            'rule_matched' => $ruleCount,
            'ai_matched' => $aiCount,
            'unmatched' => $importedFile->transactions()
                ->where('mapping_type', MappingType::Unmapped)
                ->count(),
        ];
    }

    /**
     * Run AI matching on a collection of unmapped transactions.
     */
    protected function runAiMatching(Collection $transactions): int
    {
        $chartOfAccounts = AccountHead::where('is_active', true)
            ->get()
            ->map(fn (AccountHead $head) => "{$head->id}: {$head->name} ({$head->group_name})")
            ->implode("\n");

        $descriptions = $transactions->map(fn (Transaction $t) => [
            'id' => $t->id,
            'description' => $t->description,
            'debit' => $t->debit,
            'credit' => $t->credit,
        ]);

        $prompt = "Match these transactions to account heads:\n\n";
        foreach ($descriptions as $desc) {
            $amount = $desc['debit'] ? "Debit: {$desc['debit']}" : "Credit: {$desc['credit']}";
            $prompt .= "ID {$desc['id']}: {$desc['description']} ({$amount})\n";
        }

        $agent = (new HeadMatcher)->withChartOfAccounts($chartOfAccounts);
        $response = $agent->prompt($prompt);

        $matched = 0;

        foreach ($response['matches'] ?? [] as $match) {
            if ($match['confidence'] < $this->confidenceThreshold) {
                // Still store the suggestion but mark as AI with low confidence
                $head = AccountHead::where('name', $match['suggested_head_name'])->first();

                if ($head) {
                    Transaction::where('id', $match['transaction_id'])
                        ->where('mapping_type', MappingType::Unmapped)
                        ->update([
                            'account_head_id' => $head->id,
                            'mapping_type' => MappingType::Ai,
                            'ai_confidence' => $match['confidence'],
                        ]);
                    $matched++;
                }

                continue;
            }

            $head = AccountHead::where('name', $match['suggested_head_name'])->first();

            if ($head) {
                Transaction::where('id', $match['transaction_id'])
                    ->where('mapping_type', MappingType::Unmapped)
                    ->update([
                        'account_head_id' => $head->id,
                        'mapping_type' => MappingType::Ai,
                        'ai_confidence' => $match['confidence'],
                    ]);
                $matched++;
            }
        }

        return $matched;
    }
}
