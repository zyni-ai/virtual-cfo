<?php

namespace App\Exports;

use App\Models\Transaction;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * @implements WithMapping<Transaction>
 */
class TransactionDetailSheet extends TransactionCsvExport implements FromQuery, WithEvents, WithHeadings, WithMapping, WithTitle
{
    public function title(): string
    {
        return 'Transactions';
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

                // Column widths: Date, Description, Reference, Debit, Credit, Balance, Currency, Account Head, Account Head Group, Bank/Source, Mapping Type
                $sheet->getColumnDimension('A')->setWidth(14);
                $sheet->getColumnDimension('B')->setWidth(45);
                $sheet->getColumnDimension('C')->setWidth(18);
                $sheet->getColumnDimension('D')->setWidth(15);
                $sheet->getColumnDimension('E')->setWidth(15);
                $sheet->getColumnDimension('F')->setWidth(15);
                $sheet->getColumnDimension('G')->setWidth(12);
                $sheet->getColumnDimension('H')->setWidth(28);
                $sheet->getColumnDimension('I')->setWidth(22);
                $sheet->getColumnDimension('J')->setWidth(22);
                $sheet->getColumnDimension('K')->setWidth(14);

                // Totals row — sum Debit (D) and Credit (E)
                $sheet->setCellValue("A{$totalsRow}", 'Total');
                $sheet->setCellValue("D{$totalsRow}", "=SUM(D2:D{$lastDataRow})");
                $sheet->setCellValue("E{$totalsRow}", "=SUM(E2:E{$lastDataRow})");

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
