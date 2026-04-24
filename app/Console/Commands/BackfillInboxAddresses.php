<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BackfillInboxAddresses extends Command
{
    protected $signature = 'app:backfill-inbox-addresses
                            {--dry-run : Preview what would be updated without writing to the database}';

    protected $description = 'Generate inbox_address for companies that were created without one';

    public function handle(): int
    {
        $missing = Company::whereNull('inbox_address')->get(['id', 'name']);

        if ($missing->isEmpty()) {
            $this->info('All companies already have an inbox address. Nothing to do.');

            return self::SUCCESS;
        }

        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn("Dry-run mode — no changes will be written.\n");
        }

        $this->info("Found {$missing->count()} company/companies missing an inbox address.\n");

        $rows = [];

        foreach ($missing as $company) {
            $address = $this->generateInboxAddress($company);
            $rows[] = [$company->id, $company->name ?: '(empty)', $address];

            if (! $dryRun) {
                $company->update(['inbox_address' => $address]);
            }
        }

        $this->table(['ID', 'Company Name', 'Inbox Address'], $rows);

        if ($dryRun) {
            $this->warn("\nDry run complete — re-run without --dry-run to apply.");
        } else {
            $this->info("\nBackfill complete. Updated {$missing->count()} record(s).");
        }

        return self::SUCCESS;
    }

    private function generateInboxAddress(Company $company): string
    {
        $slug = Str::slug($company->name);
        $hash = substr(hash_hmac('sha256', (string) $company->id, config('app.key')), 0, 6);
        $domain = config('services.mailgun.domain');

        return "{$slug}-{$hash}@{$domain}";
    }
}
