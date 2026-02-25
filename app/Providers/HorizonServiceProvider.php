<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     * Only authenticated users with a role (Admin or Viewer) can access the dashboard.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (User $user) {
            return $user->role !== null;
        });
    }
}
