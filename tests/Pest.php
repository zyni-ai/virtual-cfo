<?php

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

pest()->extend(Tests\TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->in('Feature');

pest()->extend(Tests\TestCase::class)
    ->in('Integration');

/**
 * Authenticate as a user for Filament tests and set up tenant context.
 */
function asUser(?User $user = null, UserRole $role = UserRole::Admin): User
{
    $user ??= User::factory()->state(['role' => $role])->create();

    $company = Company::factory()->create();
    $company->users()->attach($user, ['role' => $role->value]);

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

/**
 * Call protected getData() on a ChartWidget via reflection.
 *
 * @return array<string, mixed>
 */
function getChartData(string $widgetClass): array
{
    $widget = new $widgetClass;
    $method = new ReflectionMethod($widget, 'getData');

    return $method->invoke($widget);
}
