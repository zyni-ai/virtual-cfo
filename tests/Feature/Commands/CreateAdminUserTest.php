<?php

use App\Models\User;

describe('app:create-admin command', function () {
    it('creates a user with provided credentials', function () {
        $this->artisan('app:create-admin')
            ->expectsQuestion('Admin name', 'Test Admin')
            ->expectsQuestion('Admin email', 'test@example.com')
            ->expectsQuestion('Password', 'securepassword123')
            ->expectsOutput('Admin user test@example.com created successfully.')
            ->assertSuccessful();

        expect(User::where('email', 'test@example.com')->exists())->toBeTrue();
    });

    it('rejects duplicate email', function () {
        User::factory()->create(['email' => 'existing@example.com']);

        $this->artisan('app:create-admin')
            ->expectsQuestion('Admin name', 'Test')
            ->expectsQuestion('Admin email', 'existing@example.com')
            ->expectsQuestion('Password', 'password')
            ->assertFailed();
    });
});
