<?php

namespace App\Console\Commands;

use App\Enums\ConnectorProvider;
use App\Models\Connector;
use App\Services\Connectors\ZohoInvoiceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncZohoInvoices extends Command
{
    protected $signature = 'connectors:sync-zoho {--company= : Sync specific company by ID}';

    protected $description = 'Sync invoices from Zoho Invoice for active connectors';

    public function handle(ZohoInvoiceService $service): int
    {
        $query = Connector::query()
            ->where('provider', ConnectorProvider::Zoho)
            ->where('is_active', true);

        if ($companyId = $this->option('company')) {
            $query->where('company_id', $companyId);
        }

        $connectors = $query->get();

        if ($connectors->isEmpty()) {
            $this->info('No active Zoho connectors found.');

            return Command::SUCCESS;
        }

        $totalInvoices = 0;
        $companiesSynced = 0;
        $errors = 0;

        foreach ($connectors as $connector) {
            try {
                $count = $service->syncForCompany($connector);
                $totalInvoices += $count;
                $companiesSynced++;

                $this->line("Company #{$connector->company_id}: synced {$count} invoices.");
            } catch (\Throwable $e) {
                $errors++;

                Log::error('Failed to sync Zoho invoices for company', [
                    'company_id' => $connector->company_id,
                    'connector_id' => $connector->id,
                    'error' => $e->getMessage(),
                ]);

                $this->error("Company #{$connector->company_id}: {$e->getMessage()}");
            }
        }

        $this->info("Synced {$totalInvoices} invoices for {$companiesSynced} companies.");

        if ($errors > 0) {
            $this->warn("{$errors} companies failed to sync.");
        }

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
