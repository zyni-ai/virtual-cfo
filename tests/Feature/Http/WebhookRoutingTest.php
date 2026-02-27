<?php

use App\Http\Middleware\VerifyMailgunSignature;
use App\Models\Company;

beforeEach(function () {
    config(['services.mailgun.secret' => 'test-mailgun-secret']);
});

/**
 * Generate a valid Mailgun webhook payload with signature fields.
 *
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function webhookPayload(array $extra = []): array
{
    $timestamp = (string) time();
    $token = bin2hex(random_bytes(25));
    $signature = hash_hmac('sha256', $timestamp.$token, config('services.mailgun.secret'));

    return array_merge([
        'timestamp' => $timestamp,
        'token' => $token,
        'signature' => $signature,
    ], $extra);
}

describe('Webhook routing', function () {
    it('responds to POST inbound-email webhook with valid signature', function () {
        Company::factory()->create(['inbox_address' => 'test@inbox.example.com']);

        $response = $this->postJson('/api/v1/webhooks/inbound-email', webhookPayload([
            'recipient' => 'test@inbox.example.com',
        ]));

        $response->assertSuccessful()
            ->assertJson(['status' => 'ok']);
    });

    it('rejects GET requests to inbound-email webhook', function () {
        $response = $this->getJson('/api/v1/webhooks/inbound-email');

        $response->assertMethodNotAllowed();
    });

    it('uses the api prefix for webhook routes', function () {
        Company::factory()->create(['inbox_address' => 'test@inbox.example.com']);

        $response = $this->postJson('/api/v1/webhooks/inbound-email', webhookPayload([
            'recipient' => 'test@inbox.example.com',
        ]));

        $response->assertSuccessful();
    });

    it('does not require CSRF token for webhook routes', function () {
        Company::factory()->create(['inbox_address' => 'test@inbox.example.com']);

        $payload = webhookPayload(['recipient' => 'test@inbox.example.com']);

        $response = $this->post('/api/v1/webhooks/inbound-email', $payload, [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ]);

        $response->assertSuccessful();
    });

    it('rejects requests without valid Mailgun signature', function () {
        $response = $this->postJson('/api/v1/webhooks/inbound-email', []);

        $response->assertForbidden();
    });

    it('applies VerifyMailgunSignature middleware to inbound-email route', function () {
        $route = app('router')->getRoutes()->getByName('webhooks.inbound-email');

        expect($route->gatherMiddleware())->toContain(VerifyMailgunSignature::class);
    });
});
