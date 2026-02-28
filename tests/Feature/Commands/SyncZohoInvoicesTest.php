<?php

use App\Models\Company;
use App\Models\Connector;
use App\Services\Connectors\ZohoInvoiceService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
    Http::fake([
        '*/invoices/v3/invoices*' => Http::response(['invoices' => []]),
    ]);
});

describe('SyncZohoInvoices command', function () {
    it('syncs all active Zoho connectors', function () {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();

        Connector::factory()->zohoConnected()->create([
            'company_id' => $company1->id,
            'settings' => ['data_center' => 'in', 'client_id' => 'test-client', 'client_secret' => 'test-secret', 'organization_id' => '111'],
        ]);
        Connector::factory()->zohoConnected()->create([
            'company_id' => $company2->id,
            'settings' => ['data_center' => 'in', 'client_id' => 'test-client', 'client_secret' => 'test-secret', 'organization_id' => '222'],
        ]);

        $this->artisan('connectors:sync-zoho')
            ->assertSuccessful()
            ->expectsOutputToContain('Synced 0 invoices for 2 companies');
    });

    it('skips inactive connectors', function () {
        $company = Company::factory()->create();

        Connector::factory()->zohoConnected()->inactive()->create([
            'company_id' => $company->id,
            'settings' => ['data_center' => 'in', 'client_id' => 'test-client', 'client_secret' => 'test-secret', 'organization_id' => '111'],
        ]);

        $this->artisan('connectors:sync-zoho')
            ->assertSuccessful()
            ->expectsOutputToContain('No active Zoho connectors found');
    });

    it('filters by company when --company option is provided', function () {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();

        Connector::factory()->zohoConnected()->create([
            'company_id' => $company1->id,
            'settings' => ['data_center' => 'in', 'client_id' => 'test-client', 'client_secret' => 'test-secret', 'organization_id' => '111'],
        ]);
        Connector::factory()->zohoConnected()->create([
            'company_id' => $company2->id,
            'settings' => ['data_center' => 'in', 'client_id' => 'test-client', 'client_secret' => 'test-secret', 'organization_id' => '222'],
        ]);

        $this->artisan("connectors:sync-zoho --company={$company1->id}")
            ->assertSuccessful()
            ->expectsOutputToContain('Synced 0 invoices for 1 companies');
    });

    it('handles errors per company without stopping', function () {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();

        Connector::factory()->zohoConnected()->create([
            'company_id' => $company1->id,
            'settings' => ['data_center' => 'in', 'client_id' => 'test-client', 'client_secret' => 'test-secret', 'organization_id' => '111'],
        ]);
        Connector::factory()->zohoConnected()->create([
            'company_id' => $company2->id,
            'settings' => ['data_center' => 'in', 'client_id' => 'test-client', 'client_secret' => 'test-secret', 'organization_id' => '222'],
        ]);

        $this->partialMock(ZohoInvoiceService::class, function ($mock) {
            $mock->shouldReceive('syncForCompany')
                ->once()
                ->andThrow(new \RuntimeException('API error'));
            $mock->shouldReceive('syncForCompany')
                ->once()
                ->andReturn(0);
        });

        $this->artisan('connectors:sync-zoho')
            ->assertFailed()
            ->expectsOutputToContain('1 companies failed to sync');
    });

    it('reports no connectors found when none exist', function () {
        $this->artisan('connectors:sync-zoho')
            ->assertSuccessful()
            ->expectsOutputToContain('No active Zoho connectors found');
    });

    it('is scheduled hourly', function () {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
        $events = collect($schedule->events())->filter(function ($event) {
            return str_contains($event->command ?? '', 'connectors:sync-zoho');
        });

        expect($events)->toHaveCount(1)
            ->and($events->first()->expression)->toBe('0 * * * *');
    });
});
