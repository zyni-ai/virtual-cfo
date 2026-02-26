<?php

use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

pest()->extend(Tests\TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->in('Feature');

/**
 * Authenticate as a user for Filament tests and set up tenant context.
 */
function asUser(?User $user = null): User
{
    $user ??= User::factory()->admin()->create();

    $company = Company::factory()->create();
    $company->users()->attach($user);

    test()->actingAs($user);

    Filament::setTenant($company);
    Filament::setCurrentPanel(
        Filament::getPanel('admin'),
    );
    Filament::bootCurrentPanel();

    return $user;
}

/**
 * Get the current tenant company from Filament.
 */
function tenant(): Company
{
    return Filament::getTenant();
}
