<?php

use App\Http\Controllers\AcceptInvitationController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\ImportedFileDownloadController;
use App\Http\Controllers\ZohoOAuthCallbackController;
use App\Http\Controllers\ZohoOAuthRedirectController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthCheckController::class);

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/invitations/{token}/accept', [AcceptInvitationController::class, 'show'])
    ->name('invitations.accept');
Route::post('/invitations/{token}/accept', [AcceptInvitationController::class, 'storeNewUser'])
    ->name('invitations.accept.new');
Route::post('/invitations/{token}/accept-existing', [AcceptInvitationController::class, 'storeExistingUser'])
    ->name('invitations.accept.existing');

Route::get('/admin/imported-files/{importedFile}/download', ImportedFileDownloadController::class)
    ->middleware('auth')
    ->name('imported-files.download');

Route::get('/connectors/zoho/{company}/redirect', ZohoOAuthRedirectController::class)
    ->middleware('auth')
    ->name('connectors.zoho.redirect');

Route::get('/connectors/zoho/callback', ZohoOAuthCallbackController::class)
    ->middleware('auth')
    ->name('connectors.zoho.callback');
