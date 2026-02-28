<?php

use App\Enums\ImportSource;
use App\Enums\ImportStatus;
use App\Enums\StatementType;
use App\Jobs\ProcessImportedFile;
use App\Models\Company;
use App\Models\Connector;
use App\Models\ImportedFile;
use App\Services\Connectors\ZohoInvoiceService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();

    $this->company = Company::factory()->create();
    $this->service = new ZohoInvoiceService;
});

describe('Token refresh', function () {
    it('refreshes token when expiring soon', function () {
        $connector = Connector::factory()->create([
            'company_id' => $this->company->id,
            'token_expires_at' => now()->addMinutes(3),
            'access_token' => 'old-token',
            'settings' => ['data_center' => 'in', 'client_id' => 'test-client', 'client_secret' => 'test-secret', 'organization_id' => '12345678'],
        ]);

        Http::fake([
            '*/oauth/v2/token' => Http::response([
                'access_token' => 'new-access-token',
                'expires_in' => 3600,
            ]),
            '*/invoices/v3/invoices*' => Http::response([
                'invoices' => [],
            ]),
        ]);

        $this->service->syncForCompany($connector);

        $connector->refresh();
        expect($connector->access_token)->toBe('new-access-token');
    });

    it('does not refresh token when not expiring soon', function () {
        $connector = Connector::factory()->zohoConnected()->create([
            'company_id' => $this->company->id,
            'settings' => ['data_center' => 'in', 'client_id' => 'test-client', 'client_secret' => 'test-secret', 'organization_id' => '12345678'],
        ]);

        Http::fake([
            '*/invoices/v3/invoices*' => Http::response([
                'invoices' => [],
            ]),
        ]);

        $this->service->syncForCompany($connector);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'oauth/v2/token'));
    });

    it('throws exception when token refresh fails', function () {
        $connector = Connector::factory()->create([
            'company_id' => $this->company->id,
            'token_expires_at' => now()->addMinutes(3),
            'settings' => ['data_center' => 'in', 'client_id' => 'test-client', 'client_secret' => 'test-secret', 'organization_id' => '12345678'],
        ]);

        Http::fake([
            '*/oauth/v2/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        expect(fn () => $this->service->syncForCompany($connector))
            ->toThrow(\RuntimeException::class, 'Failed to refresh Zoho OAuth token');
    });
});

describe('Invoice fetch and ImportedFile creation', function () {
    it('fetches invoices and creates ImportedFile records', function () {
        $connector = Connector::factory()->zohoConnected()->create([
            'company_id' => $this->company->id,
            'settings' => ['data_center' => 'in', 'client_id' => 'test-client', 'client_secret' => 'test-secret', 'organization_id' => '12345678'],
        ]);

        Http::fake([
            '*/invoices/v3/invoices/INV-001*' => Http::response('%PDF-fake-content'),
            '*/invoices/v3/invoices*' => Http::response([
                'invoices' => [
                    [
                        'invoice_id' => 'INV-001',
                        'invoice_number' => 'INV-2026-001',
                    ],
                ],
            ]),
        ]);

        $count = $this->service->syncForCompany($connector);

        expect($count)->toBe(1);

        $importedFile = ImportedFile::where('company_id', $this->company->id)
            ->where('source', ImportSource::Zoho)
            ->first();

        expect($importedFile)->not->toBeNull()
            ->and($importedFile->statement_type)->toBe(StatementType::Invoice)
            ->and($importedFile->status)->toBe(ImportStatus::Pending)
            ->and($importedFile->source)->toBe(ImportSource::Zoho)
            ->and($importedFile->original_filename)->toBe('INV-2026-001.pdf')
            ->and($importedFile->source_metadata)->toBeArray()
            ->and($importedFile->source_metadata['zoho_invoice_id'])->toBe('INV-001')
            ->and($importedFile->source_metadata['zoho_org_id'])->toBe('12345678');
    });

    it('syncs multiple invoices', function () {
        $connector = Connector::factory()->zohoConnected()->create([
            'company_id' => $this->company->id,
            'settings' => ['data_center' => 'in', 'client_id' => 'test-client', 'client_secret' => 'test-secret', 'organization_id' => '12345678'],
        ]);

        Http::fake([
            '*/invoices/v3/invoices/INV-001*' => Http::response('%PDF-content-001'),
            '*/invoices/v3/invoices/INV-002*' => Http::response('%PDF-content-002'),
            '*/invoices/v3/invoices/INV-003*' => Http::response('%PDF-content-003'),
            '*/invoices/v3/invoices*' => Http::response([
                'invoices' => [
                    ['invoice_id' => 'INV-001', 'invoice_number' => 'INV-2026-001'],
                    ['invoice_id' => 'INV-002', 'invoice_number' => 'INV-2026-002'],
                    ['invoice_id' => 'INV-003', 'invoice_number' => 'INV-2026-003'],
                ],
            ]),
        ]);

        $count = $this->service->syncForCompany($connector);

        expect($count)->toBe(3);
        expect(ImportedFile::where('company_id', $this->company->id)->count())->toBe(3);
    });

    it('stores PDF in private statements directory', function () {
        $connector = Connector::factory()->zohoConnected()->create([
            'company_id' => $this->company->id,
            'settings' => ['data_center' => 'in', 'client_id' => 'test-client', 'client_secret' => 'test-secret', 'organization_id' => '12345678'],
        ]);

        Http::fake([
            '*/invoices/v3/invoices/INV-001*' => Http::response('%PDF-fake-content'),
            '*/invoices/v3/invoices*' => Http::response([
                'invoices' => [['invoice_id' => 'INV-001', 'invoice_number' => 'INV-001']],
            ]),
        ]);

        $this->service->syncForCompany($connector);

        $file = ImportedFile::first();
        expect($file->file_path)->toStartWith('statements/')
            ->and($file->file_path)->toEndWith('.pdf');

        Storage::disk('local')->assertExists($file->file_path);
    });

    it('skips invoice when PDF download fails', function () {
        $connector = Connector::factory()->zohoConnected()->create([
            'company_id' => $this->company->id,
            'settings' => ['data_center' => 'in', 'client_id' => 'test-client', 'client_secret' => 'test-secret', 'organization_id' => '12345678'],
        ]);

        Http::fake([
            '*/invoices/v3/invoices/INV-001*' => Http::response('', 500),
            '*/invoices/v3/invoices*' => Http::response([
                'invoices' => [['invoice_id' => 'INV-001', 'invoice_number' => 'INV-001']],
            ]),
        ]);

        $count = $this->service->syncForCompany($connector);

        expect($count)->toBe(0);
        expect(ImportedFile::count())->toBe(0);
    });

    it('throws when invoice fetch fails', function () {
        $connector = Connector::factory()->zohoConnected()->create([
            'company_id' => $this->company->id,
            'settings' => ['data_center' => 'in', 'client_id' => 'test-client', 'client_secret' => 'test-secret', 'organization_id' => '12345678'],
        ]);

        Http::fake([
            '*/invoices/v3/invoices*' => Http::response('Server Error', 500),
        ]);

        expect(fn () => $this->service->syncForCompany($connector))
            ->toThrow(\RuntimeException::class, 'Failed to fetch invoices from Zoho');
    });

    it('handles empty invoice list', function () {
        $connector = Connector::factory()->zohoConnected()->create([
            'company_id' => $this->company->id,
            'settings' => ['data_center' => 'in', 'client_id' => 'test-client', 'client_secret' => 'test-secret', 'organization_id' => '12345678'],
        ]);

        Http::fake([
            '*/invoices/v3/invoices*' => Http::response(['invoices' => []]),
        ]);

        $count = $this->service->syncForCompany($connector);

        expect($count)->toBe(0);
    });
});

describe('Deduplication by zoho_invoice_id', function () {
    it('skips invoices already imported', function () {
        $connector = Connector::factory()->zohoConnected()->create([
            'company_id' => $this->company->id,
            'settings' => ['data_center' => 'in', 'client_id' => 'test-client', 'client_secret' => 'test-secret', 'organization_id' => '12345678'],
        ]);

        ImportedFile::factory()->fromZoho('INV-001')->create([
            'company_id' => $this->company->id,
        ]);

        Http::fake([
            '*/invoices/v3/invoices*' => Http::response([
                'invoices' => [
                    ['invoice_id' => 'INV-001', 'invoice_number' => 'INV-001'],
                ],
            ]),
        ]);

        $count = $this->service->syncForCompany($connector);

        expect($count)->toBe(0);
        expect(ImportedFile::where('company_id', $this->company->id)->count())->toBe(1);
    });

    it('does not treat different company invoices as duplicates', function () {
        $connector = Connector::factory()->zohoConnected()->create([
            'company_id' => $this->company->id,
            'settings' => ['data_center' => 'in', 'client_id' => 'test-client', 'client_secret' => 'test-secret', 'organization_id' => '12345678'],
        ]);

        $otherCompany = Company::factory()->create();
        ImportedFile::factory()->fromZoho('INV-001')->create([
            'company_id' => $otherCompany->id,
        ]);

        Http::fake([
            '*/invoices/v3/invoices/INV-001*' => Http::response('%PDF-fake-content'),
            '*/invoices/v3/invoices*' => Http::response([
                'invoices' => [['invoice_id' => 'INV-001', 'invoice_number' => 'INV-001']],
            ]),
        ]);

        $count = $this->service->syncForCompany($connector);

        expect($count)->toBe(1);
    });
});

describe('Job dispatch', function () {
    it('dispatches ProcessImportedFile for each new invoice', function () {
        $connector = Connector::factory()->zohoConnected()->create([
            'company_id' => $this->company->id,
            'settings' => ['data_center' => 'in', 'client_id' => 'test-client', 'client_secret' => 'test-secret', 'organization_id' => '12345678'],
        ]);

        Http::fake([
            '*/invoices/v3/invoices/INV-001*' => Http::response('%PDF-content-001'),
            '*/invoices/v3/invoices/INV-002*' => Http::response('%PDF-content-002'),
            '*/invoices/v3/invoices*' => Http::response([
                'invoices' => [
                    ['invoice_id' => 'INV-001', 'invoice_number' => 'INV-001'],
                    ['invoice_id' => 'INV-002', 'invoice_number' => 'INV-002'],
                ],
            ]),
        ]);

        $this->service->syncForCompany($connector);

        Queue::assertPushed(ProcessImportedFile::class, 2);
    });

    it('does not dispatch job for duplicate invoices', function () {
        $connector = Connector::factory()->zohoConnected()->create([
            'company_id' => $this->company->id,
            'settings' => ['data_center' => 'in', 'client_id' => 'test-client', 'client_secret' => 'test-secret', 'organization_id' => '12345678'],
        ]);

        ImportedFile::factory()->fromZoho('INV-001')->create([
            'company_id' => $this->company->id,
        ]);

        Http::fake([
            '*/invoices/v3/invoices*' => Http::response([
                'invoices' => [
                    ['invoice_id' => 'INV-001', 'invoice_number' => 'INV-001'],
                ],
            ]),
        ]);

        $this->service->syncForCompany($connector);

        Queue::assertNotPushed(ProcessImportedFile::class);
    });
});

describe('last_synced_at update', function () {
    it('updates last_synced_at on connector after sync', function () {
        $connector = Connector::factory()->zohoConnected()->create([
            'company_id' => $this->company->id,
            'settings' => ['data_center' => 'in', 'client_id' => 'test-client', 'client_secret' => 'test-secret', 'organization_id' => '12345678'],
            'last_synced_at' => null,
        ]);

        Http::fake([
            '*/invoices/v3/invoices*' => Http::response(['invoices' => []]),
        ]);

        $this->freezeTime();
        $this->service->syncForCompany($connector);

        $connector->refresh();
        expect($connector->last_synced_at)->not->toBeNull()
            ->and($connector->last_synced_at->toDateTimeString())->toBe(now()->toDateTimeString());
    });

    it('passes last_synced_at as query parameter to Zoho API', function () {
        $lastSynced = now()->subHours(2);
        $connector = Connector::factory()->zohoConnected()->create([
            'company_id' => $this->company->id,
            'settings' => ['data_center' => 'in', 'client_id' => 'test-client', 'client_secret' => 'test-secret', 'organization_id' => '12345678'],
            'last_synced_at' => $lastSynced,
        ]);

        Http::fake([
            '*/invoices/v3/invoices*' => Http::response(['invoices' => []]),
        ]);

        $this->service->syncForCompany($connector);

        Http::assertSent(function ($request) use ($lastSynced) {
            return str_contains($request->url(), 'invoices/v3/invoices')
                && str_contains($request->url(), 'last_modified_time=')
                && str_contains($request->url(), urlencode($lastSynced->format('Y-m-d')));
        });
    });
});
