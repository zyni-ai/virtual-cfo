<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class TransactionExcelExport implements WithMultipleSheets
{
    public function __construct(
        public ?string $from = null,
        public ?string $until = null,
    ) {}

    /**
     * @return array<int, TransactionDetailSheet|TransactionSummarySheet>
     */
    public function sheets(): array
    {
        return [
            new TransactionDetailSheet(from: $this->from, until: $this->until),
            new TransactionSummarySheet(from: $this->from, until: $this->until),
        ];
    }
}
