<?php

namespace App\Services\RuleSuggestion;

readonly class RuleSuggestion
{
    public function __construct(
        public string $pattern,
        public int $matchCount,
        public int $accountHeadId,
        public string $accountHeadName,
        public int $importedFileId,
    ) {}
}
