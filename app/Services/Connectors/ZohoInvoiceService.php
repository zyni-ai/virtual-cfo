<?php

namespace App\Services\Connectors;

use App\Enums\ImportSource;
use App\Enums\ImportStatus;
use App\Enums\StatementType;
use App\Enums\ZohoDataCenter;
use App\Jobs\ProcessImportedFile;
use App\Models\Connector;
use App\Models\ImportedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ZohoInvoiceService
{
    /**
     * Sync invoices from Zoho for a given connector.
     *
     * @return int Number of new invoices synced
     */
    public function syncForCompany(Connector $connector): int
    {
        $this->refreshTokenIfNeeded($connector);

        $invoices = $this->fetchInvoices($connector);
        $synced = 0;

        foreach ($invoices as $invoice) {
            $zohoInvoiceId = (string) $invoice['invoice_id'];

            if ($this->isDuplicate($connector, $zohoInvoiceId)) {
                continue;
            }

            $filePath = $this->downloadInvoicePdf($connector, $zohoInvoiceId);

            if ($filePath === null) {
                Log::warning('Failed to download Zoho invoice PDF', [
                    'connector_id' => $connector->id,
                    'zoho_invoice_id' => $zohoInvoiceId,
                ]);

                continue;
            }

            $fileContent = Storage::disk('local')->get($filePath);

            $importedFile = ImportedFile::create([
                'company_id' => $connector->company_id,
                'statement_type' => StatementType::Invoice,
                'file_path' => $filePath,
                'original_filename' => ($invoice['invoice_number'] ?? $zohoInvoiceId).'.pdf',
                'file_hash' => hash('sha256', $fileContent),
                'status' => ImportStatus::Pending,
                'source' => ImportSource::Zoho,
                'source_metadata' => [
                    'zoho_invoice_id' => $zohoInvoiceId,
                    'zoho_org_id' => $connector->settings['organization_id'] ?? null,
                    'synced_at' => now()->toIso8601String(),
                ],
            ]);

            ProcessImportedFile::dispatch($importedFile);
            $synced++;
        }

        $connector->update(['last_synced_at' => now()]);

        return $synced;
    }

    /**
     * Refresh the OAuth access token if it is expiring soon.
     */
    public function refreshTokenIfNeeded(Connector $connector): void
    {
        if (! $connector->isTokenExpiringSoon()) {
            return;
        }

        $dataCenter = $this->dataCenter($connector);

        $response = Http::asForm()->post("{$dataCenter->accountsUrl()}/oauth/v2/token", [
            'refresh_token' => $connector->refresh_token,
            'client_id' => $connector->settings['client_id'],
            'client_secret' => $connector->settings['client_secret'],
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful()) {
            Log::error('Failed to refresh Zoho token', [
                'connector_id' => $connector->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('Failed to refresh Zoho OAuth token');
        }

        $data = $response->json();

        $connector->update([
            'access_token' => $data['access_token'],
            'token_expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
        ]);
    }

    /**
     * Fetch invoices from Zoho API since last sync.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function fetchInvoices(Connector $connector): array
    {
        $apiUrl = $this->dataCenter($connector)->apiUrl();
        $query = [];

        if ($connector->last_synced_at) {
            $query['last_modified_time'] = $connector->last_synced_at->format('Y-m-d\TH:i:sO');
        }

        $orgId = $connector->settings['organization_id'] ?? null;

        $response = Http::withToken($connector->access_token)
            ->withHeaders(array_filter([
                'X-com-zoho-invoice-organizationid' => $orgId,
            ]))
            ->get("{$apiUrl}/invoices/v3/invoices", $query);

        if (! $response->successful()) {
            Log::error('Failed to fetch Zoho invoices', [
                'connector_id' => $connector->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('Failed to fetch invoices from Zoho');
        }

        return $response->json('invoices') ?? [];
    }

    /**
     * Download the invoice PDF from Zoho.
     */
    protected function downloadInvoicePdf(Connector $connector, string $invoiceId): ?string
    {
        $apiUrl = $this->dataCenter($connector)->apiUrl();
        $orgId = $connector->settings['organization_id'] ?? null;

        $response = Http::withToken($connector->access_token)
            ->withHeaders(array_filter([
                'X-com-zoho-invoice-organizationid' => $orgId,
                'Accept' => 'application/pdf',
            ]))
            ->get("{$apiUrl}/invoices/v3/invoices/{$invoiceId}", [
                'accept' => 'pdf',
            ]);

        if (! $response->successful()) {
            return null;
        }

        $filePath = 'statements/'.Str::uuid().'.pdf';
        Storage::disk('local')->put($filePath, $response->body());

        return $filePath;
    }

    private function dataCenter(Connector $connector): ZohoDataCenter
    {
        return ZohoDataCenter::from($connector->settings['data_center']);
    }

    /**
     * Check if an invoice with this Zoho invoice ID has already been imported.
     */
    protected function isDuplicate(Connector $connector, string $zohoInvoiceId): bool
    {
        return ImportedFile::query()
            ->where('company_id', $connector->company_id)
            ->where('source', ImportSource::Zoho)
            ->get()
            ->contains(function (ImportedFile $file) use ($zohoInvoiceId) {
                $metadata = $file->source_metadata;

                if ($metadata === null) {
                    return false;
                }

                return ($metadata['zoho_invoice_id'] ?? null) === $zohoInvoiceId;
            });
    }
}
