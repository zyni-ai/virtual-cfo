<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyMailgunSignature
{
    /** Maximum age in seconds before a request is considered stale. */
    private const MAX_AGE_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $timestamp = $request->input('timestamp');
        $token = $request->input('token');
        $signature = $request->input('signature');

        if (! $timestamp || ! $token || ! $signature) {
            return $this->reject();
        }

        if (abs(time() - (int) $timestamp) > self::MAX_AGE_SECONDS) {
            return $this->reject();
        }

        $expectedSignature = hash_hmac(
            'sha256',
            $timestamp.$token,
            (string) config('services.mailgun.secret'),
        );

        if (! hash_equals($expectedSignature, $signature)) {
            return $this->reject();
        }

        return $next($request);
    }

    private function reject(): JsonResponse
    {
        return response()->json(['error' => 'Invalid signature'], Response::HTTP_FORBIDDEN);
    }
}
