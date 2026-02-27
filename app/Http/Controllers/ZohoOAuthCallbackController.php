<?php

namespace App\Http\Controllers;

use App\Enums\ConnectorProvider;
use App\Models\Connector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZohoOAuthCallbackController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $companyId = Crypt::decrypt($request->query('state'));
        $code = $request->query('code');

        if (! $code) {
            return $this->redirectToSettings($companyId, 'Zoho authorization was cancelled.');
        }

        $accountsUrl = config('services.zoho.accounts_url');

        $response = Http::asForm()->post("{$accountsUrl}/oauth/v2/token", [
            'code' => $code,
            'client_id' => config('services.zoho.client_id'),
            'client_secret' => config('services.zoho.client_secret'),
            'redirect_uri' => config('services.zoho.redirect_uri'),
            'grant_type' => 'authorization_code',
        ]);

        $data = $response->json();

        if (! $response->successful() || ! isset($data['access_token'])) {
            Log::error('Failed to exchange Zoho authorization code', [
                'company_id' => $companyId,
                'status' => $response->status(),
                'body' => $data,
            ]);

            $zohoError = $data['error'] ?? 'unknown_error';

            return $this->redirectToSettings($companyId, "Failed to connect to Zoho: {$zohoError}. Please try again.");
        }

        $this->upsertConnector($companyId, $data);

        return $this->redirectToSettings($companyId, status: 'connected');
    }

    /**
     * Create or update the Zoho connector, restoring if previously soft-deleted.
     *
     * @param  array<string, mixed>  $data
     */
    protected function upsertConnector(int $companyId, array $data): Connector
    {
        $connector = Connector::withTrashed()
            ->where('company_id', $companyId)
            ->where('provider', ConnectorProvider::Zoho)
            ->first();

        $attributes = [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'token_expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
            'settings' => ['organization_id' => $data['organization_id'] ?? null],
            'is_active' => true,
        ];

        if ($connector) {
            if ($connector->trashed()) {
                $connector->restore();
            }

            $connector->update($attributes);

            return $connector;
        }

        return Connector::create([
            'company_id' => $companyId,
            'provider' => ConnectorProvider::Zoho,
            ...$attributes,
        ]);
    }

    protected function redirectToSettings(int $companyId, ?string $error = null, ?string $status = null): RedirectResponse
    {
        $url = route('filament.admin.tenant.profile', ['tenant' => $companyId]);

        $query = [];

        if ($error) {
            $query['zoho_error'] = $error;
        }

        if ($status) {
            $query['zoho_status'] = $status;
        }

        if ($query) {
            $url .= '?'.http_build_query($query);
        }

        return redirect()->to($url);
    }
}
