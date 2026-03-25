<?php

namespace App\Exports;

use App\Models\AccountHead;
use App\Models\Company;
use App\Models\Transaction;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class TransactionSummarySheet implements FromCollection, WithEvents, WithHeadings, WithTitle
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
            $row['net_amount'] = abs($row['total_debit'] - $row['total_credit']);
        }

        return collect(array_values($summary));
    }

    /**
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastDataRow = $sheet->getHighestRow();
                $totalsRow = $lastDataRow + 1;

                // Column widths
                $sheet->getColumnDimension('A')->setWidth(32);
                $sheet->getColumnDimension('B')->setWidth(16);
                $sheet->getColumnDimension('C')->setWidth(16);
                $sheet->getColumnDimension('D')->setWidth(16);

                // Totals row
                $sheet->setCellValue("A{$totalsRow}", 'Total');
                $sheet->setCellValue("B{$totalsRow}", "=SUM(B2:B{$lastDataRow})");
                $sheet->setCellValue("C{$totalsRow}", "=SUM(C2:C{$lastDataRow})");
                $sheet->setCellValue("D{$totalsRow}", "=SUM(D2:D{$lastDataRow})");

                // Bold header row and totals row
                $sheet->getStyle('1:1')->getFont()->setBold(true);
                $sheet->getStyle("{$totalsRow}:{$totalsRow}")->getFont()->setBold(true);

                // Row height for all rows
                for ($i = 1; $i <= $totalsRow; $i++) {
                    $sheet->getRowDimension($i)->setRowHeight(20);
                }
            },
        ];
    }
}
