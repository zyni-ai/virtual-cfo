<?php

use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\ImportedFileDownloadController;
use App\Http\Controllers\ZohoOAuthCallbackController;
use App\Http\Controllers\ZohoOAuthRedirectController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthCheckController::class);

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/admin/imported-files/{importedFile}/download', ImportedFileDownloadController::class)
    ->middleware('auth')
    ->name('imported-files.download');

Route::get('/connectors/zoho/{company}/redirect', ZohoOAuthRedirectController::class)
    ->middleware('auth')
    ->name('connectors.zoho.redirect');

Route::get('/connectors/zoho/callback', ZohoOAuthCallbackController::class)
    ->middleware('auth')
    ->name('connectors.zoho.callback');
