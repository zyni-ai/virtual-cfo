<?php

namespace App\Exports;

use App\Models\Transaction;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * @implements WithMapping<Transaction>
 */
class TransactionDetailSheet extends TransactionCsvExport implements FromQuery, WithHeadings, WithMapping, WithTitle
{
    public function title(): string
    {
        return 'Transactions';
    }
}
