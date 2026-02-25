<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

pest()->extend(Tests\TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->in('Feature');

/**
 * Authenticate as a user for Filament tests.
 */
function asUser(?User $user = null): User
{
    $user ??= User::factory()->admin()->create();

    test()->actingAs($user);

    return $user;
}
