<?php

namespace App\Http\Middleware;

use App\Models\LoginSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class UpdateLastActiveAt
{
    private const DEBOUNCE_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $cacheKey = "last_active:{$request->user()->id}";

            if (Cache::add($cacheKey, true, self::DEBOUNCE_SECONDS)) {
                LoginSession::activeForUser($request->user()->id)
                    ->limit(1)
                    ->update(['last_active_at' => now()]);
            }
        }

        return $next($request);
    }
}
