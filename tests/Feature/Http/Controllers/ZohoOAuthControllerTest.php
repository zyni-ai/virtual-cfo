<?php

use App\Enums\ConnectorProvider;
use App\Models\Connector;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

describe('ZohoOAuthRedirectController', function () {
    it('redirects to Zoho auth URL with correct parameters', function () {
        asUser();
        $company = tenant();

        $response = $this->get(route('connectors.zoho.redirect'));

        $response->assertRedirect();

        $redirectUrl = $response->headers->get('Location');
        $accountsUrl = config('services.zoho.accounts_url');

        expect($redirectUrl)->toContain("{$accountsUrl}/oauth/v2/auth")
            ->and($redirectUrl)->toContain('response_type=code')
            ->and($redirectUrl)->toContain('scope=ZohoInvoice.invoices.READ')
            ->and($redirectUrl)->toContain('access_type=offline')
            ->and($redirectUrl)->toContain('prompt=consent')
            ->and($redirectUrl)->toContain('state=');
    });

    it('encrypts company_id in state parameter', function () {
        asUser();
        $company = tenant();

        $response = $this->get(route('connectors.zoho.redirect'));
        $redirectUrl = $response->headers->get('Location');

        parse_str(parse_url($redirectUrl, PHP_URL_QUERY), $queryParams);
        $decryptedState = Crypt::decrypt($queryParams['state']);

        expect($decryptedState)->toBe($company->id);
    });

    it('rejects unauthenticated access', function () {
        $response = $this->get(route('connectors.zoho.redirect'));

        $response->assertRedirect();
        expect($response->headers->get('Location'))->toContain('login');
    });
});

describe('ZohoOAuthCallbackController', function () {
    it('exchanges code and creates a new Connector', function () {
        $user = asUser();
        $company = tenant();

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

        $connector = Connector::where('company_id', $company->id)
            ->where('provider', ConnectorProvider::Zoho)
            ->first();

        expect($connector)->not->toBeNull()
            ->and($connector->access_token)->toBe('zoho-access-token-123')
            ->and($connector->refresh_token)->toBe('zoho-refresh-token-456')
            ->and($connector->is_active)->toBeTrue()
            ->and($connector->settings['organization_id'])->toBe('98765');
    });

    it('updates existing Connector on reconnection', function () {
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

    it('reactivates a soft-deleted Connector on reconnection', function () {
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

        Http::fake([
            '*/oauth/v2/token' => Http::response(['error' => 'invalid_code'], 400),
        ]);

        $state = Crypt::encrypt($company->id);

        $response = $this->get(route('connectors.zoho.callback', [
            'code' => 'bad-code',
            'state' => $state,
        ]));

        $response->assertRedirect();
        expect(Connector::where('company_id', $company->id)->count())->toBe(0);
    });

    it('redirects with error when no code is provided', function () {
        $user = asUser();
        $company = tenant();

        $state = Crypt::encrypt($company->id);

        $response = $this->get(route('connectors.zoho.callback', [
            'state' => $state,
        ]));

        $response->assertRedirect();
        expect(Connector::where('company_id', $company->id)->count())->toBe(0);
    });

    it('rejects unauthenticated access', function () {
        $response = $this->get(route('connectors.zoho.callback', [
            'code' => 'test',
            'state' => 'test',
        ]));

        $response->assertRedirect();
        expect($response->headers->get('Location'))->toContain('login');
    });

    it('sends correct parameters in token exchange request', function () {
        $user = asUser();
        $company = tenant();

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
                && str_contains($request->body(), 'code=my-auth-code');
        });
    });
});
