<?php

use App\Filament\Pages\Tenancy\EditCompanySettings;
use App\Models\Connector;
use App\Services\Connectors\ZohoInvoiceService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

describe('Connector settings in EditCompanySettings', function () {
    beforeEach(function () {
        asUser();
    });

    it('shows connect button when no Zoho connector exists', function () {
        livewire(EditCompanySettings::class)
            ->assertSuccessful()
            ->assertActionVisible('connectZoho');
    });

    it('hides connect button when Zoho is connected', function () {
        Connector::factory()->zohoConnected()->create([
            'company_id' => tenant()->id,
            'settings' => ['organization_id' => '12345'],
        ]);

        livewire(EditCompanySettings::class)
            ->assertActionHidden('connectZoho');
    });

    it('shows disconnect and sync actions when Zoho is connected', function () {
        Connector::factory()->zohoConnected()->create([
            'company_id' => tenant()->id,
            'settings' => ['organization_id' => '12345'],
        ]);

        livewire(EditCompanySettings::class)
            ->assertSuccessful()
            ->assertActionVisible('syncZoho')
            ->assertActionVisible('disconnectZoho');
    });

    it('hides sync and disconnect when not connected', function () {
        livewire(EditCompanySettings::class)
            ->assertActionHidden('syncZoho')
            ->assertActionHidden('disconnectZoho');
    });

    it('can disconnect Zoho connector', function () {
        $connector = Connector::factory()->zohoConnected()->create([
            'company_id' => tenant()->id,
            'settings' => ['organization_id' => '12345'],
        ]);

        livewire(EditCompanySettings::class)
            ->callAction('disconnectZoho')
            ->assertNotified('Zoho Invoice disconnected.');

        $connector->refresh();
        expect($connector->is_active)->toBeFalse()
            ->and($connector->deleted_at)->not->toBeNull();
    });

    it('can trigger sync now action', function () {
        Storage::fake('local');
        Queue::fake();

        Connector::factory()->zohoConnected()->create([
            'company_id' => tenant()->id,
            'settings' => ['organization_id' => '12345'],
        ]);

        Http::fake([
            '*/invoices/v3/invoices*' => Http::response(['invoices' => []]),
        ]);

        livewire(EditCompanySettings::class)
            ->callAction('syncZoho')
            ->assertNotified('Synced 0 invoices from Zoho.');
    });

    it('shows error notification when sync fails', function () {
        Connector::factory()->zohoConnected()->create([
            'company_id' => tenant()->id,
            'settings' => ['organization_id' => '12345'],
        ]);

        $this->mock(ZohoInvoiceService::class, function ($mock) {
            $mock->shouldReceive('syncForCompany')
                ->once()
                ->andThrow(new \RuntimeException('API connection failed'));
        });

        livewire(EditCompanySettings::class)
            ->callAction('syncZoho')
            ->assertNotified('Sync failed: API connection failed');
    });
});
