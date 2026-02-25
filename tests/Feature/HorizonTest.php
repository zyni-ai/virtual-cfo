<?php

use App\Models\User;

describe('Horizon Configuration', function () {
    it('has horizon config published with required keys', function () {
        $config = config('horizon');

        expect($config)->not->toBeNull()
            ->and($config)->toBeArray()
            ->and($config)->toHaveKeys([
                'environments',
                'defaults',
                'prefix',
                'middleware',
                'trim',
                'waits',
            ]);
    });

    it('uses the default queue for workers', function () {
        $defaults = config('horizon.defaults.supervisor-1');

        expect($defaults)->toBeArray()
            ->and($defaults['queue'])->toBe(['default']);
    });
});

describe('Horizon Dashboard Authorization', function () {
    it('allows admin users to access the dashboard', function () {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/horizon');

        $response->assertSuccessful();
    });

    it('allows viewer users to access the dashboard', function () {
        $viewer = User::factory()->viewer()->create();

        $response = $this->actingAs($viewer)->get('/horizon');

        $response->assertSuccessful();
    });

    it('denies access to users without a role', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/horizon');

        $response->assertForbidden();
    });

    it('denies access to unauthenticated users', function () {
        $response = $this->get('/horizon');

        $response->assertForbidden();
    });
});
