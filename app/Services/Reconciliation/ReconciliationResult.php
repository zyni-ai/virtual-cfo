<?php

namespace App\Services\Reconciliation;

class ReconciliationResult
{
    public int $matched = 0;

    public int $partiallyMatched = 0;

    public int $flagged = 0;

    public int $unreconciled = 0;

    public function total(): int
    {
        return $this->matched + $this->partiallyMatched + $this->flagged + $this->unreconciled;
    }
}
