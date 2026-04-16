<?php

namespace App\Http\Middleware;

use App\Models\User;
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
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Filament::getTenant();

        if ($tenant) {
            DB::statement("SET app.current_company_id = '{$tenant->getKey()}'");
            $this->recordLastUsedCompany($request, $tenant->getKey());
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

    private function recordLastUsedCompany(Request $request, int|string $companyId): void
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return;
        }

        if ((int) $user->last_used_company_id === (int) $companyId) {
            return;
        }

        $user->updateQuietly(['last_used_company_id' => $companyId]);
    }
}
