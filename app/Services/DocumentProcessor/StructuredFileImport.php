<?php

namespace App\Services\DocumentProcessor;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class StructuredFileImport implements ToArray, WithHeadingRow
{
    /** @var array<int, array<string, mixed>> */
    protected array $rows = [];

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function array(array $rows): void
    {
        $this->rows = array_filter($rows, function (array $row) {
            return count(array_filter($row, fn ($value) => $value !== null && $value !== '')) > 0;
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRows(): array
    {
        return $this->rows;
    }
}
