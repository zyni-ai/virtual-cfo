<?php

namespace App\Http\Controllers;

use App\Enums\ConnectorProvider;
use App\Enums\ZohoDataCenter;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;

class ZohoOAuthRedirectController
{
    public function __invoke(Company $company): RedirectResponse
    {
        $this->authorize($company);

        $connector = $company->connectors()
            ->where('provider', ConnectorProvider::Zoho)
            ->firstOrFail();

        $dataCenter = ZohoDataCenter::from($connector->settings['data_center']);

        $params = http_build_query([
            'response_type' => 'code',
            'client_id' => $connector->settings['client_id'],
            'scope' => 'ZohoInvoice.invoices.READ',
            'redirect_uri' => config('services.zoho.redirect_uri'),
            'state' => Crypt::encrypt($company->id),
            'access_type' => 'offline',
            'prompt' => 'consent',
        ]);

        return redirect()->away("{$dataCenter->accountsUrl()}/oauth/v2/auth?{$params}");
    }

    private function authorize(Company $company): void
    {
        abort_unless(
            auth()->user()->companies()->where('companies.id', $company->id)->exists(),
            403,
        );
    }
}
