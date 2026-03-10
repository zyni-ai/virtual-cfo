<?php

namespace App\Providers;

use App\Listeners\RecordLoginSession;
use App\Listeners\RecordLogoutSession;
use App\Models\Transaction;
use App\Observers\TransactionObserver;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(Login::class, RecordLoginSession::class);
        Event::listen(Logout::class, RecordLogoutSession::class);

        Transaction::observe(TransactionObserver::class);

        FilamentTimezone::set(config('app.display_timezone'));
    }
}
