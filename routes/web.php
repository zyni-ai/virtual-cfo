<?php

use App\Http\Controllers\AcceptInvitationController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\ImportedFileDownloadController;
use App\Http\Controllers\ZohoOAuthCallbackController;
use App\Http\Controllers\ZohoOAuthRedirectController;
use Illuminate\Support\Facades\DB;
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

if (app()->environment('local')) {
    Route::prefix('dev/mail-preview')->group(function () {
        Route::get('/invitation', function () {
            DB::beginTransaction();
            $invitation = App\Models\Invitation::factory()->create();
            $html = (new App\Mail\InvitationMail($invitation))->render();
            DB::rollBack();

            return $html;
        });

        Route::get('/import-failed', function () {
            DB::beginTransaction();
            $file = App\Models\ImportedFile::factory()->failed('PDF parsing error')->create();
            $html = (new App\Notifications\ImportFailedNotification($file))
                ->toMail($file->uploader)
                ->render();
            DB::rollBack();

            return $html;
        });

        Route::get('/role-changed', function () {
            DB::beginTransaction();
            $user = App\Models\User::factory()->create();
            $html = (new App\Notifications\MemberRoleChangedNotification(
                companyName: 'Zysk Technologies',
                newRole: 'Admin',
            ))->toMail($user)
                ->render();
            DB::rollBack();

            return $html;
        });

        Route::get('/invitation-accepted', function () {
            DB::beginTransaction();
            $invitation = App\Models\Invitation::factory()->accepted()->create();
            $html = (new App\Notifications\InvitationAcceptedNotification($invitation))
                ->toMail($invitation->inviter)
                ->render();
            DB::rollBack();

            return $html;
        });
    });
}
