<?php

namespace App\Http\Controllers;

use App\Enums\ConnectorProvider;
use App\Enums\ZohoDataCenter;
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

        $connector = Connector::withTrashed()
            ->where('company_id', $companyId)
            ->where('provider', ConnectorProvider::Zoho)
            ->firstOrFail();

        $dataCenter = ZohoDataCenter::from($connector->settings['data_center']);

        $response = Http::asForm()->post("{$dataCenter->accountsUrl()}/oauth/v2/token", [
            'code' => $code,
            'client_id' => $connector->settings['client_id'],
            'client_secret' => $connector->settings['client_secret'],
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

        $this->updateConnectorTokens($connector, $data);

        return $this->redirectToSettings($companyId, status: 'connected');
    }

    /**
     * Update the Zoho connector with token data, merging into existing settings.
     *
     * @param  array<string, mixed>  $data
     */
    protected function updateConnectorTokens(Connector $connector, array $data): Connector
    {
        if ($connector->trashed()) {
            $connector->restore();
        }

        $connector->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'token_expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
            'settings' => array_merge($connector->settings ?? [], [
                'organization_id' => $data['organization_id'] ?? null,
            ]),
            'is_active' => true,
        ]);

        return $connector;
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
