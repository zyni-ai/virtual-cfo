<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo('/admin/login');
        $middleware->validateCsrfTokens(except: [
            'api/v1/webhooks/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Always return JSON for API routes regardless of Accept header.
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e): bool {
            return $request->is('api/*') || $request->expectsJson();
        });

        // Attach the authenticated user and their company to every error report
        // so logs are searchable by who triggered the error.
        $exceptions->context(function (): array {
            $user = auth()->user();

            if ($user === null) {
                return [];
            }

            return [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'company_id' => $user->company_id ?? null,
            ];
        });

        // ModelNotFoundException → 404. Never expose the model class name in the
        // response body (security: leaks internal model structure to clients).
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Not found.'], 404);
            }
        });

        // InvalidArgumentException → 422. Used by ReconciliationService to guard
        // against invalid state transitions (e.g. confirming a non-Suggested match).
        $exceptions->render(function (InvalidArgumentException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        });

        // AuthorizationException → 403 JSON for API consumers.
        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'This action is unauthorized.'], 403);
            }
        });

        // 404s are expected (users bookmark stale URLs, scanners probe paths).
        // Suppress them from the error log to avoid noise.
        $exceptions->dontReport([
            NotFoundHttpException::class,
        ]);
    })->create();
