<?php

use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\ImportedFileDownloadController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthCheckController::class);

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/admin/imported-files/{importedFile}/download', ImportedFileDownloadController::class)
    ->middleware('auth')
    ->name('imported-files.download');
