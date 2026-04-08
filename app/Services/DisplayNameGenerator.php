<?php

namespace App\Services;

use App\Models\ImportedFile;
use Carbon\Carbon;

class DisplayNameGenerator
{
    public function generate(ImportedFile $file): string
    {
        $period = $this->resolvePeriod($file);
        $variant = $file->card_variant ?? $file->creditCard?->name;

        if ($variant) {
            return "{$file->bank_name}_{$variant}_{$period}";
        }

        return "{$file->bank_name}_{$period}";
    }

    private function resolvePeriod(ImportedFile $file): string
    {
        if (! $file->statement_period) {
            return $file->created_at->format('M_Y');
        }

        return $this->extractEndMonth($file->statement_period);
    }

    private function extractEndMonth(string $period): string
    {
        $dateToParse = str_contains($period, ' to ')
            ? trim(substr($period, strpos($period, ' to ') + 4))
            : $period;

        try {
            return Carbon::parse($dateToParse)->format('M_Y');
        } catch (\Exception) {
            return $period;
        }
    }
}
