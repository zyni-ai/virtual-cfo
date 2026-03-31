<?php

namespace App\Services;

use App\Models\ImportedFile;

class DisplayNameGenerator
{
    public function generate(ImportedFile $file): string
    {
        $period = $this->resolvePeriod($file);

        if ($file->credit_card_id && $file->creditCard) {
            return "{$file->bank_name}_{$file->creditCard->name}_{$period}";
        }

        return "{$file->bank_name}_{$period}";
    }

    private function resolvePeriod(ImportedFile $file): string
    {
        if ($file->statement_period) {
            return $file->statement_period;
        }

        return $file->created_at->format('M Y');
    }
}
