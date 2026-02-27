<?php

use App\Http\Controllers\Api\InboundEmailController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/webhooks')->group(function () {
    Route::post('/inbound-email', InboundEmailController::class)->name('webhooks.inbound-email');
});
