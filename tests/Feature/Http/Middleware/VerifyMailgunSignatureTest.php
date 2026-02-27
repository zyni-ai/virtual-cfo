<?php

use App\Http\Middleware\VerifyMailgunSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Generate a valid Mailgun webhook signature.
 *
 * @return array{timestamp: string, token: string, signature: string}
 */
function mailgunSignature(?string $secret = null, ?int $timestamp = null): array
{
    $secret ??= config('services.mailgun.secret');
    $timestamp ??= time();
    $token = bin2hex(random_bytes(25));

    return [
        'timestamp' => (string) $timestamp,
        'token' => $token,
        'signature' => hash_hmac('sha256', $timestamp.$token, $secret),
    ];
}

beforeEach(function () {
    config(['services.mailgun.secret' => 'test-mailgun-secret']);
});

describe('VerifyMailgunSignature middleware', function () {
    it('passes requests with a valid signature', function () {
        $params = mailgunSignature();

        $request = Request::create('/api/v1/webhooks/inbound-email', 'POST', $params);

        $middleware = new VerifyMailgunSignature;
        $response = $middleware->handle($request, fn () => new JsonResponse(['status' => 'ok']));

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getData(true))->toBe(['status' => 'ok']);
    });

    it('rejects requests with an invalid signature', function () {
        $params = mailgunSignature();
        $params['signature'] = 'invalid-signature-value';

        $request = Request::create('/api/v1/webhooks/inbound-email', 'POST', $params);

        $middleware = new VerifyMailgunSignature;
        $response = $middleware->handle($request, fn () => new JsonResponse(['status' => 'ok']));

        expect($response->getStatusCode())->toBe(403)
            ->and($response->getData(true))->toHaveKey('error');
    });

    it('rejects requests with a stale timestamp', function () {
        $staleTimestamp = time() - 400;
        $params = mailgunSignature(timestamp: $staleTimestamp);

        $request = Request::create('/api/v1/webhooks/inbound-email', 'POST', $params);

        $middleware = new VerifyMailgunSignature;
        $response = $middleware->handle($request, fn () => new JsonResponse(['status' => 'ok']));

        expect($response->getStatusCode())->toBe(403);
    });

    it('rejects requests with missing fields', function (array $fields) {
        $request = Request::create('/api/v1/webhooks/inbound-email', 'POST', $fields);

        $middleware = new VerifyMailgunSignature;
        $response = $middleware->handle($request, fn () => new JsonResponse(['status' => 'ok']));

        expect($response->getStatusCode())->toBe(403);
    })->with([
        'missing all fields' => [[]],
        'missing signature' => [['timestamp' => '1234567890', 'token' => 'abc']],
        'missing token' => [['timestamp' => '1234567890', 'signature' => 'abc']],
        'missing timestamp' => [['token' => 'abc', 'signature' => 'abc']],
    ]);

    it('rejects requests signed with wrong secret', function () {
        $params = mailgunSignature(secret: 'wrong-secret');

        $request = Request::create('/api/v1/webhooks/inbound-email', 'POST', $params);

        $middleware = new VerifyMailgunSignature;
        $response = $middleware->handle($request, fn () => new JsonResponse(['status' => 'ok']));

        expect($response->getStatusCode())->toBe(403);
    });

    it('accepts requests within the 5-minute window', function () {
        $recentTimestamp = time() - 290;
        $params = mailgunSignature(timestamp: $recentTimestamp);

        $request = Request::create('/api/v1/webhooks/inbound-email', 'POST', $params);

        $middleware = new VerifyMailgunSignature;
        $response = $middleware->handle($request, fn () => new JsonResponse(['status' => 'ok']));

        expect($response->getStatusCode())->toBe(200);
    });
});
