<?php

namespace App\Services\RecurringPatterns;

use App\Enums\MappingType;
use App\Models\Company;
use App\Models\RecurringPattern;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

class RecurringPatternService
{
    protected const AMOUNT_TOLERANCE = 0.10;

    protected const MIN_OCCURRENCES = 3;

    protected const STALE_MONTHS = 6;

    /**
     * Noise words to remove during description normalization.
     *
     * @var array<int, string>
     */
    protected const NOISE_WORDS = [
        'neft', 'upi', 'rtgs', 'imps', 'inr', 'ref',
        'ach', 'ecs', 'nach', 'cms', 'pos', 'atm',
    ];

    /**
     * Normalize a transaction description by removing numbers, noise words,
     * and extra whitespace.
     */
    public function normalizeDescription(string $description): string
    {
        $normalized = mb_strtolower($description);

        // Remove numbers (sequences of digits, optionally with dashes/slashes between)
        $normalized = preg_replace('/\d+/', '', $normalized);

        // Remove noise words as whole words
        foreach (self::NOISE_WORDS as $word) {
            $normalized = preg_replace('/\b'.preg_quote($word, '/').'\b/', '', $normalized);
        }

        // Remove special characters except letters and spaces
        $normalized = preg_replace('/[^a-z\s]/', '', $normalized);

        // Collapse multiple spaces and trim
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        return trim($normalized);
    }

    /**
     * Detect recurring patterns from a company's transactions.
     * Finds transactions with 3+ occurrences of the same normalized description
     * across different months.
     *
     * @return int Number of new patterns created
     */
    public function detectPatterns(Company $company): int
    {
        $transactions = Transaction::where('company_id', $company->id)
            ->whereNotNull('description')
            ->get();

        // Group by normalized description
        /** @var array<string, array<int, array{date: \Carbon\Carbon|null, amount: float|null}>> $groups */
        $groups = [];
        foreach ($transactions as $transaction) {
            $normalized = $this->normalizeDescription($transaction->description);

            if ($normalized === '') {
                continue;
            }

            $groups[$normalized][] = [
                'date' => $transaction->date,
                'amount' => $transaction->amount,
            ];
        }

        $newCount = 0;

        foreach ($groups as $pattern => $occurrences) {
            if (count($occurrences) < self::MIN_OCCURRENCES) {
                continue;
            }

            // Check that occurrences span different months
            $months = collect($occurrences)
                ->filter(fn (array $o) => $o['date'] !== null)
                ->map(fn (array $o) => $o['date']->format('Y-m'))
                ->unique();

            if ($months->count() < 2) {
                continue;
            }

            $amounts = collect($occurrences)
                ->pluck('amount')
                ->filter()
                ->values();

            $avgAmount = $amounts->isNotEmpty() ? round($amounts->avg(), 2) : null;

            $lastSeen = collect($occurrences)
                ->pluck('date')
                ->filter()
                ->max();

            $frequency = $this->detectFrequency($occurrences);

            $existing = RecurringPattern::where('company_id', $company->id)
                ->where('description_pattern', $pattern)
                ->whereNull('bank_format')
                ->first();

            if ($existing) {
                $existing->update([
                    'occurrence_count' => count($occurrences),
                    'avg_amount' => $avgAmount,
                    'last_seen_at' => $lastSeen,
                    'frequency' => $frequency,
                ]);
            } else {
                RecurringPattern::create([
                    'company_id' => $company->id,
                    'description_pattern' => $pattern,
                    'avg_amount' => $avgAmount,
                    'occurrence_count' => count($occurrences),
                    'last_seen_at' => $lastSeen,
                    'frequency' => $frequency,
                ]);
                $newCount++;
            }
        }

        return $newCount;
    }

    /**
     * Check if a transaction matches an active recurring pattern.
     * If the pattern has an account_head_id, auto-map the transaction.
     */
    public function matchTransaction(Transaction $transaction): ?RecurringPattern
    {
        $normalized = $this->normalizeDescription($transaction->description ?? '');

        if ($normalized === '') {
            return null;
        }

        $pattern = RecurringPattern::where('company_id', $transaction->company_id)
            ->where('description_pattern', $normalized)
            ->where('is_active', true)
            ->first();

        if (! $pattern) {
            return null;
        }

        // Check amount tolerance if pattern has avg_amount and transaction has an amount
        if ($pattern->avg_amount !== null && $transaction->amount !== null) {
            $tolerance = (float) $pattern->avg_amount * self::AMOUNT_TOLERANCE;
            $diff = abs($transaction->amount - (float) $pattern->avg_amount);

            if ($diff > $tolerance) {
                return null;
            }
        }

        // Auto-map if pattern has an account head
        if ($pattern->account_head_id !== null) {
            $transaction->update([
                'account_head_id' => $pattern->account_head_id,
                'mapping_type' => MappingType::Auto,
                'recurring_pattern_id' => $pattern->id,
            ]);
        } else {
            $transaction->update([
                'recurring_pattern_id' => $pattern->id,
            ]);
        }

        return $pattern;
    }

    /**
     * Deactivate patterns that haven't been seen in over 6 months.
     *
     * @return int Number of patterns deactivated
     */
    public function deactivateStalePatterns(Company $company): int
    {
        $cutoff = Carbon::now()->subMonths(self::STALE_MONTHS);

        return RecurringPattern::where('company_id', $company->id)
            ->where('is_active', true)
            ->where('last_seen_at', '<', $cutoff)
            ->update(['is_active' => false]);
    }

    /**
     * Detect the frequency of occurrences based on date gaps.
     *
     * @param  array<int, array{date: \Carbon\Carbon|null, amount: float|null}>  $occurrences
     */
    protected function detectFrequency(array $occurrences): string
    {
        $dates = collect($occurrences)
            ->pluck('date')
            ->filter()
            ->sort()
            ->values();

        if ($dates->count() < 2) {
            return 'irregular';
        }

        $gaps = [];
        for ($i = 1; $i < $dates->count(); $i++) {
            $gaps[] = $dates[$i - 1]->diffInDays($dates[$i]);
        }

        $avgGap = array_sum($gaps) / count($gaps);

        return match (true) {
            $avgGap <= 45 => 'monthly',
            $avgGap <= 120 => 'quarterly',
            $avgGap <= 400 => 'annual',
            default => 'irregular',
        };
    }
}
