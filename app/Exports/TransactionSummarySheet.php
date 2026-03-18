<?php

namespace App\Exports;

use App\Models\AccountHead;
use App\Models\Company;
use App\Models\Transaction;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class TransactionSummarySheet implements FromCollection, WithHeadings, WithTitle
{
    /** @param Builder<Transaction>|null $baseQuery */
    public function __construct(
        public ?string $from = null,
        public ?string $until = null,
        public ?Builder $baseQuery = null,
    ) {}

    public function title(): string
    {
        return 'Summary';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Account Head',
            'Account Head Group',
            'Total Debit',
            'Total Credit',
            'Net Amount',
        ];
    }

    /**
     * @return Collection<int, mixed>
     */
    public function collection(): Collection
    {
        if ($this->baseQuery) {
            $query = $this->baseQuery
                ->clone()
                ->whereNotNull('account_head_id')
                ->with('accountHead');
        } else {
            /** @var Company $tenant */
            $tenant = Filament::getTenant();

            $query = Transaction::query()
                ->where('company_id', $tenant->id)
                ->whereNotNull('account_head_id')
                ->with('accountHead');
        }

        if ($this->from) {
            $query->whereDate('date', '>=', $this->from);
        }

        if ($this->until) {
            $query->whereDate('date', '<=', $this->until);
        }

        $transactions = $query->get();

        $summary = [];
        foreach ($transactions as $transaction) {
            $headId = $transaction->account_head_id;
            if ($headId === null) {
                continue;
            }

            if (! isset($summary[$headId])) {
                /** @var AccountHead|null $accountHead */
                $accountHead = $transaction->accountHead;
                $summary[$headId] = [
                    'account_head' => $accountHead?->name,
                    'group_name' => $accountHead?->group_name,
                    'total_debit' => 0.0,
                    'total_credit' => 0.0,
                    'net_amount' => 0.0,
                ];
            }

            if ($transaction->debit !== null) {
                $summary[$headId]['total_debit'] += (float) $transaction->debit;
            }
            if ($transaction->credit !== null) {
                $summary[$headId]['total_credit'] += (float) $transaction->credit;
            }
        }

        foreach ($summary as &$row) {
            $row['net_amount'] = $row['total_credit'] - $row['total_debit'];
        }

        return collect(array_values($summary));
    }
}
