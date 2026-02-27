<?php

use App\Http\Controllers\Api\InboundEmailController;
use App\Http\Middleware\VerifyMailgunSignature;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/webhooks')->group(function () {
    Route::post('/inbound-email', InboundEmailController::class)
        ->middleware(VerifyMailgunSignature::class)
        ->name('webhooks.inbound-email');
});
