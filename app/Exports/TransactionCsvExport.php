<?php

namespace App\Exports;

use App\Models\AccountHead;
use App\Models\Company;
use App\Models\ImportedFile;
use App\Models\Transaction;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * @implements WithMapping<Transaction>
 */
class TransactionCsvExport implements FromQuery, WithHeadings, WithMapping
{
    /** @param Builder<Transaction>|null $baseQuery */
    public function __construct(
        public ?string $from = null,
        public ?string $until = null,
        public ?Builder $baseQuery = null,
    ) {}

    /**
     * @return Builder<Transaction>
     */
    public function query(): Builder
    {
        if ($this->baseQuery) {
            $query = $this->baseQuery
                ->clone()
                ->whereNotNull('account_head_id')
                ->with(['accountHead', 'importedFile'])
                ->orderBy('date');
        } else {
            /** @var Company $tenant */
            $tenant = Filament::getTenant();

            $query = Transaction::query()
                ->where('company_id', $tenant->id)
                ->whereNotNull('account_head_id')
                ->with(['accountHead', 'importedFile'])
                ->orderBy('date');
        }

        if ($this->from) {
            $query->whereDate('date', '>=', $this->from);
        }

        if ($this->until) {
            $query->whereDate('date', '<=', $this->until);
        }

        return $query;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Date',
            'Description',
            'Reference',
            'Debit',
            'Credit',
            'Balance',
            'Currency',
            'Account Head',
            'Account Head Group',
            'Bank/Source',
            'Mapping Type',
        ];
    }

    /**
     * @param  Transaction  $row
     * @return array<int, string|float|null>
     */
    public function map($row): array
    {
        /** @var Carbon $date */
        $date = $row->date;
        /** @var AccountHead|null $accountHead */
        $accountHead = $row->accountHead;
        /** @var ImportedFile|null $importedFile */
        $importedFile = $row->importedFile;

        return [
            $date->format('d M Y'),
            $row->description,
            $row->reference_number,
            $row->debit !== null ? (float) $row->debit : null,
            $row->credit !== null ? (float) $row->credit : null,
            $row->balance !== null ? (float) $row->balance : null,
            $row->currency,
            $accountHead?->name,
            $accountHead?->group_name,
            $importedFile?->bank_name,
            $row->mapping_type?->value,
        ];
    }
}
