<?php

namespace App\Services\DataPrivacy;

class Pseudonymizer
{
    /**
     * Maps from original value to token (e.g., '9876543210' => '[PHONE_1]').
     *
     * @var array<string, string>
     */
    protected array $valueToToken = [];

    /**
     * Maps from token back to original value.
     *
     * @var array<string, string>
     */
    protected array $tokenToValue = [];

    /**
     * Counter per PII type for generating sequential tokens.
     *
     * @var array<string, int>
     */
    protected array $counters = [];

    /**
     * PII patterns ordered by specificity (longest/most specific first).
     *
     * @var array<string, string>
     */
    protected const PATTERNS = [
        'GSTIN' => '/\d{2}[A-Z]{5}\d{4}[A-Z]\d[Z][A-Z\d]/',
        'PAN' => '/\b[A-Z]{5}[0-9]{4}[A-Z]\b/',
        'IFSC' => '/\b[A-Z]{4}0[A-Z0-9]{6}\b/',
        'EMAIL' => '/\b[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}\b/',
        'UPI' => '/\b[\w.]+@[a-z]+\b/',
        'PHONE' => '/\b[6-9]\d{9}\b/',
        'ACCT' => '/\b\d{10,18}\b/',
    ];

    /**
     * Replace PII in text with stable pseudonymized tokens.
     */
    public function mask(string $text): string
    {
        foreach (self::PATTERNS as $type => $pattern) {
            $text = preg_replace_callback($pattern, function (array $match) use ($type): string {
                return $this->getOrCreateToken($type, $match[0]);
            }, $text);
        }

        return $text;
    }

    /**
     * Reverse pseudonymized tokens back to original values.
     */
    public function unmask(string $text): string
    {
        return str_replace(
            array_keys($this->tokenToValue),
            array_values($this->tokenToValue),
            $text
        );
    }

    /**
     * Clear all internal maps to start fresh.
     */
    public function reset(): void
    {
        $this->valueToToken = [];
        $this->tokenToValue = [];
        $this->counters = [];
    }

    /**
     * Get existing token for a value, or create a new one.
     */
    protected function getOrCreateToken(string $type, string $value): string
    {
        if (isset($this->valueToToken[$value])) {
            return $this->valueToToken[$value];
        }

        $counter = ($this->counters[$type] ?? 0) + 1;
        $this->counters[$type] = $counter;

        $token = "[{$type}_{$counter}]";

        $this->valueToToken[$value] = $token;
        $this->tokenToValue[$token] = $value;

        return $token;
    }
}
