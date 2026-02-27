<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SetTenantDatabaseContext
{
    /**
     * Set the PostgreSQL session variable for RLS tenant isolation.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Filament::getTenant();

        if ($tenant) {
            DB::statement("SET app.current_company_id = '{$tenant->getKey()}'");
        }

        return $next($request);
    }

    /**
     * Reset the session variable to prevent connection pool leakage.
     */
    public function terminate(Request $request, Response $response): void
    {
        DB::statement("SET app.current_company_id = ''");
    }
}
