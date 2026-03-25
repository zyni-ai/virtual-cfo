<?php

use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\ImportedFileDownloadController;
use App\Http\Controllers\ZohoOAuthCallbackController;
use App\Http\Controllers\ZohoOAuthRedirectController;
use App\Mail\InvitationMail;
use App\Models\ImportedFile;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\ImportFailedNotification;
use App\Notifications\InvitationAcceptedNotification;
use App\Notifications\MemberRoleChangedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthCheckController::class);

Route::get('/', function () {
    return redirect('/admin');
});

// Legacy invitation routes — redirect to Filament panel
Route::get('/invitations/{token}/accept', fn (string $token) => redirect("/admin/invitations/{$token}/accept"))
    ->name('invitations.accept.legacy');

Route::get('/admin/imported-files/{importedFile}/download', ImportedFileDownloadController::class)
    ->middleware('auth')
    ->name('imported-files.download')
    ->withTrashed();

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
            $invitation = Invitation::factory()->create();
            $html = (new InvitationMail($invitation))->render();
            DB::rollBack();

            return $html;
        });

        Route::get('/import-failed', function () {
            DB::beginTransaction();
            $file = ImportedFile::factory()->failed('PDF parsing error')->create();
            $html = (new ImportFailedNotification($file))
                ->toMail($file->uploader)
                ->render();
            DB::rollBack();

            return $html;
        });

        Route::get('/role-changed', function () {
            DB::beginTransaction();
            $user = User::factory()->create();
            $html = (new MemberRoleChangedNotification(
                companyName: 'Zysk Technologies',
                newRole: 'Admin',
            ))->toMail($user)
                ->render();
            DB::rollBack();

            return $html;
        });

        Route::get('/invitation-accepted', function () {
            DB::beginTransaction();
            $invitation = Invitation::factory()->accepted()->create();
            $html = (new InvitationAcceptedNotification($invitation))
                ->toMail($invitation->inviter)
                ->render();
            DB::rollBack();

            return $html;
        });
    });
}
