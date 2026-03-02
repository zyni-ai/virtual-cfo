<?php

namespace App\Listeners;

use App\Models\LoginSession;
use App\Support\UserAgentParser;
use Illuminate\Auth\Events\Login;

class RecordLoginSession
{
    public function handle(Login $event): void
    {
        $request = request();
        $userAgent = $request->userAgent() ?? '';
        $parsed = UserAgentParser::parse($userAgent);

        LoginSession::create([
            'user_id' => $event->user->getAuthIdentifier(),
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => $userAgent,
            'device_type' => $parsed['device_type'],
            'browser' => $parsed['browser'],
            'os' => $parsed['os'],
            'logged_in_at' => now(),
            'last_active_at' => now(),
        ]);
    }
}
