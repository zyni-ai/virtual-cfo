<?php

namespace App\Services\TallyImport;

class TallyImportResult
{
    public int $groupsCreated = 0;

    public int $groupsUpdated = 0;

    public int $ledgersCreated = 0;

    public int $ledgersUpdated = 0;

    public int $bankAccountsCreated = 0;

    public int $bankAccountsUpdated = 0;

    public bool $companyUpdated = false;

    /** @var array<int, string> */
    public array $errors = [];

    /** @var array<int, string> */
    public array $warnings = [];

    public function totalCreated(): int
    {
        return $this->groupsCreated + $this->ledgersCreated + $this->bankAccountsCreated;
    }

    public function totalUpdated(): int
    {
        return $this->groupsUpdated + $this->ledgersUpdated + $this->bankAccountsUpdated;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }
}
