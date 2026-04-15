<?php

use App\Enums\ConnectorProvider;
use App\Enums\ZohoDataCenter;
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
        ]);

        livewire(EditCompanySettings::class)
            ->assertActionHidden('connectZoho');
    });

    it('shows disconnect and sync actions when Zoho is connected', function () {
        Connector::factory()->zohoConnected()->create([
            'company_id' => tenant()->id,
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

    it('creates connector with credentials and data center when connecting Zoho', function () {
        livewire(EditCompanySettings::class)
            ->callAction('connectZoho', data: [
                'data_center' => ZohoDataCenter::Us->value,
                'client_id' => 'test-client-id',
                'client_secret' => 'test-client-secret',
            ])
            ->assertHasNoActionErrors();

        $connector = Connector::where('company_id', tenant()->id)
            ->where('provider', ConnectorProvider::Zoho)
            ->first();

        expect($connector)->not->toBeNull()
            ->and($connector->settings['data_center'])->toBe(ZohoDataCenter::Us->value)
            ->and($connector->settings['client_id'])->toBe('test-client-id')
            ->and($connector->settings['client_secret'])->toBe('test-client-secret')
            ->and($connector->is_active)->toBeFalse();
    });

    it('validates required fields when connecting Zoho', function () {
        livewire(EditCompanySettings::class)
            ->callAction('connectZoho', data: [
                'data_center' => '',
                'client_id' => '',
                'client_secret' => '',
            ])
            ->assertHasActionErrors([
                'data_center' => 'required',
                'client_id' => 'required',
                'client_secret' => 'required',
            ]);

        expect(Connector::where('company_id', tenant()->id)->count())->toBe(0);
    });

    it('can disconnect Zoho connector', function () {
        $connector = Connector::factory()->zohoConnected()->create([
            'company_id' => tenant()->id,
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
        ]);

        $this->mock(ZohoInvoiceService::class, function ($mock) {
            $mock->shouldReceive('syncForCompany')
                ->once()
                ->andThrow(new RuntimeException('API connection failed'));
        });

        livewire(EditCompanySettings::class)
            ->callAction('syncZoho')
            ->assertNotified('Sync failed: API connection failed');
    });
});
