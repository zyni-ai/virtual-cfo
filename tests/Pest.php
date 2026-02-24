<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class)->in('Feature');

/**
 * Authenticate as a user for Filament tests.
 */
function asUser(?User $user = null): User
{
    $user ??= User::factory()->create();

    test()->actingAs($user);

    return $user;
}
