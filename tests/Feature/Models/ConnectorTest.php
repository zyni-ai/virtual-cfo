<?php

use App\Enums\ConnectorProvider;
use App\Models\Company;
use App\Models\Connector;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;

describe('Connector factory', function () {
    it('creates a connector with valid defaults', function () {
        $connector = Connector::factory()->create();

        expect($connector->exists)->toBeTrue()
            ->and($connector->provider)->toBe(ConnectorProvider::Zoho)
            ->and($connector->is_active)->toBeTrue()
            ->and($connector->access_token)->toBeString()
            ->and($connector->refresh_token)->toBeString();
    });

    it('creates a zoho connected connector', function () {
        $connector = Connector::factory()->zohoConnected()->create();

        expect($connector->provider)->toBe(ConnectorProvider::Zoho)
            ->and($connector->is_active)->toBeTrue()
            ->and($connector->last_synced_at)->not->toBeNull();
    });

    it('creates an expired connector', function () {
        $connector = Connector::factory()->expired()->create();

        expect($connector->isTokenExpired())->toBeTrue();
    });

    it('creates an inactive connector', function () {
        $connector = Connector::factory()->inactive()->create();

        expect($connector->is_active)->toBeFalse();
    });
});

describe('Connector relationships', function () {
    it('belongs to a company', function () {
        $connector = Connector::factory()->create();

        expect($connector->company)->toBeInstanceOf(Company::class);
    });
});

describe('Connector encryption', function () {
    it('encrypts and decrypts access_token', function () {
        $connector = Connector::factory()->create(['access_token' => 'secret-token-123']);

        $fresh = Connector::find($connector->id);
        expect($fresh->access_token)->toBe('secret-token-123');
    });

    it('encrypts and decrypts refresh_token', function () {
        $connector = Connector::factory()->create(['refresh_token' => 'refresh-secret-456']);

        $fresh = Connector::find($connector->id);
        expect($fresh->refresh_token)->toBe('refresh-secret-456');
    });

    it('encrypts and decrypts settings as array', function () {
        $settings = ['organization_id' => '12345', 'webhook_url' => 'https://example.com'];
        $connector = Connector::factory()->create(['settings' => $settings]);

        $fresh = Connector::find($connector->id);
        expect($fresh->settings)->toBe($settings);
    });
});

describe('Connector token expiry', function () {
    it('reports expired when token_expires_at is in the past', function () {
        $connector = Connector::factory()->create(['token_expires_at' => now()->subMinute()]);

        expect($connector->isTokenExpired())->toBeTrue();
    });

    it('reports not expired when token_expires_at is in the future', function () {
        $connector = Connector::factory()->create(['token_expires_at' => now()->addHour()]);

        expect($connector->isTokenExpired())->toBeFalse();
    });

    it('reports expired when token_expires_at is null', function () {
        $connector = Connector::factory()->create(['token_expires_at' => null]);

        expect($connector->isTokenExpired())->toBeTrue();
    });

    it('reports expiring soon within threshold', function () {
        $connector = Connector::factory()->create(['token_expires_at' => now()->addMinutes(3)]);

        expect($connector->isTokenExpiringSoon(5))->toBeTrue();
    });

    it('reports not expiring soon when well ahead', function () {
        $connector = Connector::factory()->create(['token_expires_at' => now()->addHour()]);

        expect($connector->isTokenExpiringSoon(5))->toBeFalse();
    });
});

describe('Connector soft deletes', function () {
    it('uses the SoftDeletes trait', function () {
        expect(in_array(SoftDeletes::class, class_uses_recursive(Connector::class)))->toBeTrue();
    });

    it('can be soft deleted and restored', function () {
        $connector = Connector::factory()->create();
        $connector->delete();

        expect(Connector::find($connector->id))->toBeNull();
        expect(Connector::withTrashed()->find($connector->id))->not->toBeNull();

        $connector->restore();
        expect(Connector::find($connector->id))->not->toBeNull();
    });
});

describe('Connector unique constraint', function () {
    it('prevents duplicate active connectors for the same company and provider', function () {
        $company = Company::factory()->create();

        Connector::factory()->create([
            'company_id' => $company->id,
            'provider' => ConnectorProvider::Zoho,
        ]);

        expect(fn () => Connector::factory()->create([
            'company_id' => $company->id,
            'provider' => ConnectorProvider::Zoho,
        ]))->toThrow(QueryException::class);
    });

    it('allows same provider for different companies', function () {
        $connectorA = Connector::factory()->create(['provider' => ConnectorProvider::Zoho]);
        $connectorB = Connector::factory()->create(['provider' => ConnectorProvider::Zoho]);

        expect($connectorA->exists)->toBeTrue()
            ->and($connectorB->exists)->toBeTrue();
    });
});
