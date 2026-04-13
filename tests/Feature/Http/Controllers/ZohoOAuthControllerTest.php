<?php

use App\Enums\ConnectorProvider;
use App\Enums\ZohoDataCenter;
use App\Models\Company;
use App\Models\Connector;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

describe('ZohoOAuthRedirectController', function () {
    it('redirects to Zoho auth URL with correct parameters', function () {
        asUser();
        $company = tenant();

        $connector = Connector::factory()->create([
            'company_id' => $company->id,
            'is_active' => false,
        ]);

        $response = $this->get(route('connectors.zoho.redirect', ['company' => $company]));

        $response->assertRedirect();

        $redirectUrl = $response->headers->get('Location');
        $dataCenter = ZohoDataCenter::from($connector->settings['data_center']);

        expect($redirectUrl)->toContain("{$dataCenter->accountsUrl()}/oauth/v2/auth")
            ->and($redirectUrl)->toContain('response_type=code')
            ->and($redirectUrl)->toContain('client_id='.urlencode($connector->settings['client_id']))
            ->and($redirectUrl)->toContain('scope=ZohoInvoice.invoices.READ')
            ->and($redirectUrl)->toContain('access_type=offline')
            ->and($redirectUrl)->toContain('prompt=consent')
            ->and($redirectUrl)->toContain('state=');
    });

    it('uses the data center URL from connector settings', function () {
        asUser();
        $company = tenant();

        Connector::factory()->create([
            'company_id' => $company->id,
            'settings' => [
                'data_center' => ZohoDataCenter::Us->value,
                'client_id' => 'us-client-id',
                'client_secret' => 'us-secret',
            ],
            'is_active' => false,
        ]);

        $response = $this->get(route('connectors.zoho.redirect', ['company' => $company]));
        $redirectUrl = $response->headers->get('Location');

        expect($redirectUrl)->toContain('accounts.zoho.com/oauth/v2/auth')
            ->and($redirectUrl)->not->toContain('accounts.zoho.in');
    });

    it('encrypts company_id in state parameter', function () {
        asUser();
        $company = tenant();

        Connector::factory()->create([
            'company_id' => $company->id,
            'is_active' => false,
        ]);

        $response = $this->get(route('connectors.zoho.redirect', ['company' => $company]));
        $redirectUrl = $response->headers->get('Location');

        parse_str(parse_url($redirectUrl, PHP_URL_QUERY), $queryParams);
        $decryptedState = Crypt::decrypt($queryParams['state']);

        expect($decryptedState)->toBe($company->id);
    });

    it('uses client_id from connector settings', function () {
        asUser();
        $company = tenant();

        Connector::factory()->create([
            'company_id' => $company->id,
            'settings' => [
                'data_center' => ZohoDataCenter::India->value,
                'client_id' => 'tenant-specific-client-id',
                'client_secret' => 'tenant-specific-secret',
            ],
            'is_active' => false,
        ]);

        $response = $this->get(route('connectors.zoho.redirect', ['company' => $company]));
        $redirectUrl = $response->headers->get('Location');

        expect($redirectUrl)->toContain('client_id=tenant-specific-client-id');
    });

    it('rejects unauthenticated access', function () {
        $company = Company::factory()->create();

        $response = $this->get(route('connectors.zoho.redirect', ['company' => $company]));

        $response->assertRedirect();
        expect($response->headers->get('Location'))->toContain('login');
    });

    it('rejects access to a company the user does not belong to', function () {
        asUser();
        $otherCompany = Company::factory()->create();

        $response = $this->get(route('connectors.zoho.redirect', ['company' => $otherCompany]));

        $response->assertForbidden();
    });

    it('returns 404 when no connector exists for the company', function () {
        asUser();
        $company = tenant();

        $response = $this->get(route('connectors.zoho.redirect', ['company' => $company]));

        $response->assertNotFound();
    });
});

describe('ZohoOAuthCallbackController', function () {
    it('exchanges code and updates existing connector with tokens', function () {
        $user = asUser();
        $company = tenant();

        $connector = Connector::factory()->create([
            'company_id' => $company->id,
            'settings' => [
                'data_center' => ZohoDataCenter::India->value,
                'client_id' => 'my-client-id',
                'client_secret' => 'my-client-secret',
            ],
            'is_active' => false,
        ]);

        Http::fake([
            '*/oauth/v2/token' => Http::response([
                'access_token' => 'zoho-access-token-123',
                'refresh_token' => 'zoho-refresh-token-456',
                'expires_in' => 3600,
                'organization_id' => '98765',
            ]),
        ]);

        $state = Crypt::encrypt($company->id);

        $response = $this->get(route('connectors.zoho.callback', [
            'code' => 'auth-code-abc',
            'state' => $state,
        ]));

        $response->assertRedirect();

        $connector->refresh();

        expect($connector->access_token)->toBe('zoho-access-token-123')
            ->and($connector->refresh_token)->toBe('zoho-refresh-token-456')
            ->and($connector->is_active)->toBeTrue()
            ->and($connector->settings['organization_id'])->toBe('98765')
            ->and($connector->settings['client_id'])->toBe('my-client-id')
            ->and($connector->settings['client_secret'])->toBe('my-client-secret')
            ->and($connector->settings['data_center'])->toBe(ZohoDataCenter::India->value);
    });

    it('preserves all settings after token exchange', function () {
        $user = asUser();
        $company = tenant();

        Connector::factory()->create([
            'company_id' => $company->id,
            'settings' => [
                'data_center' => ZohoDataCenter::Eu->value,
                'client_id' => 'preserved-client-id',
                'client_secret' => 'preserved-client-secret',
                'organization_id' => 'old-org-id',
            ],
            'is_active' => false,
        ]);

        Http::fake([
            '*/oauth/v2/token' => Http::response([
                'access_token' => 'new-token',
                'refresh_token' => 'new-refresh',
                'expires_in' => 3600,
                'organization_id' => 'new-org-id',
            ]),
        ]);

        $state = Crypt::encrypt($company->id);

        $this->get(route('connectors.zoho.callback', [
            'code' => 'auth-code',
            'state' => $state,
        ]));

        $connector = Connector::where('company_id', $company->id)
            ->where('provider', ConnectorProvider::Zoho)
            ->first();

        expect($connector->settings['client_id'])->toBe('preserved-client-id')
            ->and($connector->settings['client_secret'])->toBe('preserved-client-secret')
            ->and($connector->settings['data_center'])->toBe(ZohoDataCenter::Eu->value)
            ->and($connector->settings['organization_id'])->toBe('new-org-id');
    });

    it('sends token exchange to correct regional accounts URL', function () {
        $user = asUser();
        $company = tenant();

        Connector::factory()->create([
            'company_id' => $company->id,
            'settings' => [
                'data_center' => ZohoDataCenter::Us->value,
                'client_id' => 'us-client',
                'client_secret' => 'us-secret',
            ],
            'is_active' => false,
        ]);

        Http::fake([
            '*/oauth/v2/token' => Http::response([
                'access_token' => 'test-token',
                'refresh_token' => 'test-refresh',
                'expires_in' => 3600,
            ]),
        ]);

        $state = Crypt::encrypt($company->id);

        $this->get(route('connectors.zoho.callback', [
            'code' => 'auth-code',
            'state' => $state,
        ]));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'accounts.zoho.com/oauth/v2/token');
        });
    });

    it('updates existing connector on reconnection', function () {
        $user = asUser();
        $company = tenant();

        $existing = Connector::factory()->zohoConnected()->create([
            'company_id' => $company->id,
            'access_token' => 'old-token',
        ]);

        Http::fake([
            '*/oauth/v2/token' => Http::response([
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 3600,
                'organization_id' => '11111',
            ]),
        ]);

        $state = Crypt::encrypt($company->id);

        $this->get(route('connectors.zoho.callback', [
            'code' => 'auth-code-xyz',
            'state' => $state,
        ]));

        $existing->refresh();
        expect($existing->access_token)->toBe('new-access-token')
            ->and($existing->refresh_token)->toBe('new-refresh-token')
            ->and($existing->is_active)->toBeTrue();

        expect(Connector::where('company_id', $company->id)->count())->toBe(1);
    });

    it('reactivates a soft-deleted connector on reconnection', function () {
        $user = asUser();
        $company = tenant();

        $deleted = Connector::factory()->zohoConnected()->create([
            'company_id' => $company->id,
        ]);
        $deleted->delete();

        Http::fake([
            '*/oauth/v2/token' => Http::response([
                'access_token' => 'reactivated-token',
                'refresh_token' => 'reactivated-refresh',
                'expires_in' => 3600,
                'organization_id' => '22222',
            ]),
        ]);

        $state = Crypt::encrypt($company->id);

        $this->get(route('connectors.zoho.callback', [
            'code' => 'auth-code-reactivate',
            'state' => $state,
        ]));

        $connector = Connector::where('company_id', $company->id)
            ->where('provider', ConnectorProvider::Zoho)
            ->first();

        expect($connector)->not->toBeNull()
            ->and($connector->access_token)->toBe('reactivated-token')
            ->and($connector->is_active)->toBeTrue()
            ->and($connector->deleted_at)->toBeNull();
    });

    it('redirects with error when token exchange fails', function () {
        $user = asUser();
        $company = tenant();

        Connector::factory()->create([
            'company_id' => $company->id,
            'settings' => [
                'data_center' => ZohoDataCenter::India->value,
                'client_id' => 'my-client-id',
                'client_secret' => 'my-client-secret',
            ],
            'is_active' => false,
        ]);

        Http::fake([
            '*/oauth/v2/token' => Http::response(['error' => 'invalid_code'], 400),
        ]);

        $state = Crypt::encrypt($company->id);

        $response = $this->get(route('connectors.zoho.callback', [
            'code' => 'bad-code',
            'state' => $state,
        ]));

        $response->assertRedirect();

        $connector = Connector::where('company_id', $company->id)->first();
        expect($connector->is_active)->toBeFalse();
    });

    it('redirects with error when no code is provided', function () {
        $user = asUser();
        $company = tenant();

        Connector::factory()->create([
            'company_id' => $company->id,
            'is_active' => false,
        ]);

        $state = Crypt::encrypt($company->id);

        $response = $this->get(route('connectors.zoho.callback', [
            'state' => $state,
        ]));

        $response->assertRedirect();
    });

    it('rejects unauthenticated access', function () {
        $response = $this->get(route('connectors.zoho.callback', [
            'code' => 'test',
            'state' => 'test',
        ]));

        $response->assertRedirect();
        expect($response->headers->get('Location'))->toContain('login');
    });

    it('sends connector credentials in token exchange request', function () {
        $user = asUser();
        $company = tenant();

        Connector::factory()->create([
            'company_id' => $company->id,
            'settings' => [
                'data_center' => ZohoDataCenter::India->value,
                'client_id' => 'tenant-client-id',
                'client_secret' => 'tenant-client-secret',
            ],
            'is_active' => false,
        ]);

        Http::fake([
            '*/oauth/v2/token' => Http::response([
                'access_token' => 'test-token',
                'refresh_token' => 'test-refresh',
                'expires_in' => 3600,
            ]),
        ]);

        $state = Crypt::encrypt($company->id);

        $this->get(route('connectors.zoho.callback', [
            'code' => 'my-auth-code',
            'state' => $state,
        ]));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'oauth/v2/token')
                && str_contains($request->body(), 'grant_type=authorization_code')
                && str_contains($request->body(), 'code=my-auth-code')
                && str_contains($request->body(), 'client_id=tenant-client-id')
                && str_contains($request->body(), 'client_secret=tenant-client-secret');
        });
    });
});
