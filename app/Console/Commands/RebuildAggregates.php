<?php

namespace App\Console\Commands;

use App\Services\AggregateService;
use Illuminate\Console\Command;

class RebuildAggregates extends Command
{
    protected $signature = 'aggregates:rebuild
        {--company= : Rebuild for a specific company ID}
        {--month= : Rebuild for a specific month (YYYY-MM)}';

    protected $description = 'Rebuild transaction aggregate totals from source transactions';

    public function handle(AggregateService $service): int
    {
        $companyId = $this->option('company') ? (int) $this->option('company') : null;
        $month = $this->option('month');

        $this->info('Rebuilding transaction aggregates...');

        if ($companyId) {
            $this->info("  Filtering by company ID: {$companyId}");
        }

        if ($month) {
            $this->info("  Filtering by month: {$month}");
        }

        $service->rebuild($companyId, $month);

        $this->info('Transaction aggregates rebuilt successfully.');

        return self::SUCCESS;
    }
}
