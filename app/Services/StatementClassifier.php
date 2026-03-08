<?php

namespace App\Services;

use App\Enums\StatementType;
use App\Models\ImportedFile;

class StatementClassifier
{
    /**
     * Re-classify an ImportedFile from its source_metadata and filename.
     * Returns null if no classification signals are found.
     */
    public function classifyFromMetadata(ImportedFile $file): ?StatementType
    {
        return $this->classify($file->source_metadata, $file->original_filename);
    }

    /**
     * Classify from email metadata and/or filename.
     * Tries email context first (subject + body), falls back to filename.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function classify(?array $metadata, string $filename): ?StatementType
    {
        if (is_array($metadata)) {
            $text = strtolower(trim(($metadata['subject'] ?? '').' '.($metadata['body_text'] ?? '')));

            if ($text !== '') {
                $result = $this->classifyText($text);
                if ($result !== null) {
                    return $result;
                }
            }
        }

        return $this->classifyByFilename($filename);
    }

    /**
     * Classify an attachment by its filename to determine the statement type.
     */
    public function classifyByFilename(string $filename): ?StatementType
    {
        $name = strtolower(pathinfo($filename, PATHINFO_FILENAME));

        return $this->classifyText($name);
    }

    /**
     * Classify text (email context or filename) into a statement type.
     * Credit card patterns are checked before generic statement patterns
     * so "Credit Card Statement" matches CreditCard, not Bank.
     */
    public function classifyText(string $text): ?StatementType
    {
        $patternMap = [
            [StatementType::CreditCard, ['credit[_\-\s]?card', 'cc[_\-\s]?statement']],
            [StatementType::Invoice, ['inv', 'invoice', 'tax[_\-\s]?invoice', 'bill', 'debit[_\-\s]?note', 'credit[_\-\s]?note']],
            [StatementType::Bank, ['statement', 'bank[_\-\s]?statement', 'account[_\-\s]?statement']],
        ];

        foreach ($patternMap as [$type, $patterns]) {
            if ($this->matchesAny($text, $patterns)) {
                return $type;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $patterns
     */
    private function matchesAny(string $text, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match('/(?:^|[\W_])'.$pattern.'(?:$|[\W_\d])/', $text)) {
                return true;
            }
        }

        return false;
    }
}
