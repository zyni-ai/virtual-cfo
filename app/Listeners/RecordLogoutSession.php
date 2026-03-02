<?php

namespace App\Listeners;

use App\Models\LoginSession;
use Illuminate\Auth\Events\Logout;

class RecordLogoutSession
{
    public function handle(Logout $event): void
    {
        LoginSession::activeForUser($event->user->getAuthIdentifier())
            ->limit(1)
            ->update(['logged_out_at' => now()]);
    }
}
