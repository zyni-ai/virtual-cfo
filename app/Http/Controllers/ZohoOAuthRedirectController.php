<?php

namespace App\Http\Controllers;

use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;

class ZohoOAuthRedirectController
{
    public function __invoke(): RedirectResponse
    {
        $company = Filament::getTenant();

        $params = http_build_query([
            'response_type' => 'code',
            'client_id' => config('services.zoho.client_id'),
            'scope' => 'ZohoInvoice.invoices.READ',
            'redirect_uri' => config('services.zoho.redirect_uri'),
            'state' => Crypt::encrypt($company->id),
            'access_type' => 'offline',
            'prompt' => 'consent',
        ]);

        $accountsUrl = config('services.zoho.accounts_url');

        return redirect()->away("{$accountsUrl}/oauth/v2/auth?{$params}");
    }
}
