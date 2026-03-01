<?php

use App\Listeners\RecordLoginSession;
use App\Listeners\RecordLogoutSession;
use App\Models\LoginSession;
use App\Models\User;
use App\Support\UserAgentParser;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Cache;

describe('Login Session Tracking', function () {
    describe('Login event listener', function () {
        it('creates session record when login event fires', function () {
            $user = User::factory()->admin()->create();

            request()->headers->set('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

            $listener = new RecordLoginSession;
            $listener->handle(new Login('web', $user, false));

            $session = LoginSession::where('user_id', $user->id)->first();
            expect($session)->not->toBeNull()
                ->and($session->ip_address)->not->toBeEmpty()
                ->and($session->user_agent)->toContain('Chrome')
                ->and($session->browser)->toBe('Chrome')
                ->and($session->os)->toBe('Windows')
                ->and($session->device_type)->toBe('Desktop')
                ->and($session->logged_in_at)->not->toBeNull()
                ->and($session->last_active_at)->not->toBeNull()
                ->and($session->logged_out_at)->toBeNull();
        });

        it('records correct browser and OS for different user agents', function () {
            $user = User::factory()->admin()->create();

            request()->headers->set('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15');

            $listener = new RecordLoginSession;
            $listener->handle(new Login('web', $user, false));

            $session = LoginSession::where('user_id', $user->id)->first();
            expect($session->browser)->toBe('Safari')
                ->and($session->os)->toBe('macOS')
                ->and($session->device_type)->toBe('Desktop');
        });
    });

    describe('Logout event listener', function () {
        it('updates logged_out_at when logout event fires', function () {
            $user = User::factory()->admin()->create();
            $session = LoginSession::factory()->create([
                'user_id' => $user->id,
                'logged_in_at' => now()->subHour(),
                'last_active_at' => now()->subMinutes(10),
            ]);

            expect($session->logged_out_at)->toBeNull();

            $listener = new RecordLogoutSession;
            $listener->handle(new Logout('web', $user));

            expect($session->fresh()->logged_out_at)->not->toBeNull();
        });

        it('only closes the most recent active session', function () {
            $user = User::factory()->admin()->create();

            $closedTime = now()->subDays(2);
            $oldSession = LoginSession::factory()->create([
                'user_id' => $user->id,
                'logged_in_at' => now()->subDays(3),
                'last_active_at' => now()->subDays(2),
                'logged_out_at' => $closedTime,
            ]);

            $currentSession = LoginSession::factory()->create([
                'user_id' => $user->id,
                'logged_in_at' => now()->subHour(),
                'last_active_at' => now()->subMinutes(5),
            ]);

            $listener = new RecordLogoutSession;
            $listener->handle(new Logout('web', $user));

            expect($currentSession->fresh()->logged_out_at)->not->toBeNull();

            $refreshedOld = $oldSession->fresh();
            expect($refreshedOld->logged_out_at->timestamp)->toBe($closedTime->timestamp);
        });
    });

    describe('UserAgentParser', function () {
        it('detects Chrome on Windows', function () {
            $result = UserAgentParser::parse('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

            expect($result['browser'])->toBe('Chrome')
                ->and($result['os'])->toBe('Windows')
                ->and($result['device_type'])->toBe('Desktop');
        });

        it('detects Safari on macOS', function () {
            $result = UserAgentParser::parse('Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15');

            expect($result['browser'])->toBe('Safari')
                ->and($result['os'])->toBe('macOS')
                ->and($result['device_type'])->toBe('Desktop');
        });

        it('detects Firefox on Linux', function () {
            $result = UserAgentParser::parse('Mozilla/5.0 (X11; Linux x86_64; rv:120.0) Gecko/20100101 Firefox/120.0');

            expect($result['browser'])->toBe('Firefox')
                ->and($result['os'])->toBe('Linux')
                ->and($result['device_type'])->toBe('Desktop');
        });

        it('detects Edge on Windows', function () {
            $result = UserAgentParser::parse('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0');

            expect($result['browser'])->toBe('Edge')
                ->and($result['os'])->toBe('Windows')
                ->and($result['device_type'])->toBe('Desktop');
        });

        it('detects mobile iPhone', function () {
            $result = UserAgentParser::parse('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1');

            expect($result['device_type'])->toBe('Mobile')
                ->and($result['os'])->toBe('iOS')
                ->and($result['browser'])->toBe('Safari');
        });

        it('detects Android tablet', function () {
            $result = UserAgentParser::parse('Mozilla/5.0 (Linux; Android 13; SM-X700) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

            expect($result['device_type'])->toBe('Tablet')
                ->and($result['os'])->toBe('Android')
                ->and($result['browser'])->toBe('Chrome');
        });
    });

    describe('LastActiveAt middleware', function () {
        it('updates last_active_at on authenticated requests', function () {
            $user = User::factory()->admin()->create();
            asUser($user);

            $session = LoginSession::factory()->create([
                'user_id' => $user->id,
                'logged_in_at' => now()->subHour(),
                'last_active_at' => now()->subHour(),
            ]);

            $this->get('/admin');

            expect($session->fresh()->last_active_at->gt($session->last_active_at))->toBeTrue();
        });

        it('throttles updates to every 5 minutes', function () {
            $user = User::factory()->admin()->create();
            asUser($user);

            $session = LoginSession::factory()->create([
                'user_id' => $user->id,
                'logged_in_at' => now()->subHour(),
                'last_active_at' => now()->subHour(),
            ]);

            $this->get('/admin');
            $firstUpdate = $session->fresh()->last_active_at;

            $this->travel(2)->minutes();
            $this->get('/admin');

            expect($session->fresh()->last_active_at->equalTo($firstUpdate))->toBeTrue();
        });

        it('updates again after cache expires', function () {
            $user = User::factory()->admin()->create();
            asUser($user);

            $session = LoginSession::factory()->create([
                'user_id' => $user->id,
                'logged_in_at' => now()->subHour(),
                'last_active_at' => now()->subHour(),
            ]);

            $this->get('/admin');
            $firstUpdate = $session->fresh()->last_active_at;

            $this->travel(6)->minutes();
            Cache::forget("last_active:{$user->id}");
            $this->get('/admin');

            expect($session->fresh()->last_active_at->gt($firstUpdate))->toBeTrue();
        });
    });
});
