<?php

describe('Webhook routing', function () {
    it('responds to POST inbound-email webhook', function () {
        $response = $this->postJson('/api/v1/webhooks/inbound-email', []);

        $response->assertSuccessful()
            ->assertJson(['status' => 'ok']);
    });

    it('rejects GET requests to inbound-email webhook', function () {
        $response = $this->getJson('/api/v1/webhooks/inbound-email');

        $response->assertMethodNotAllowed();
    });

    it('uses the api prefix for webhook routes', function () {
        $response = $this->postJson('/api/v1/webhooks/inbound-email');

        $response->assertSuccessful();
    });

    it('does not require CSRF token for webhook routes', function () {
        $response = $this->post('/api/v1/webhooks/inbound-email', [], [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ]);

        $response->assertSuccessful();
    });
});
