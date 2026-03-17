<?php

namespace App\Exports;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class TransactionExcelExport implements WithMultipleSheets
{
    /** @param Builder<Transaction>|null $baseQuery */
    public function __construct(
        public ?string $from = null,
        public ?string $until = null,
        public ?Builder $baseQuery = null,
    ) {}

    /**
     * @return array<int, TransactionDetailSheet|TransactionSummarySheet>
     */
    public function sheets(): array
    {
        return [
            new TransactionDetailSheet(from: $this->from, until: $this->until, baseQuery: $this->baseQuery),
            new TransactionSummarySheet(from: $this->from, until: $this->until, baseQuery: $this->baseQuery),
        ];
    }
}
