<?php

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;

describe('UserRole enum', function () {
    it('has Admin, Accountant, and Viewer cases', function () {
        expect(UserRole::cases())->toHaveCount(3)
            ->and(UserRole::Admin->value)->toBe('admin')
            ->and(UserRole::Accountant->value)->toBe('accountant')
            ->and(UserRole::Viewer->value)->toBe('viewer');
    });

    it('returns correct labels', function () {
        expect(UserRole::Admin->getLabel())->toBe('Admin')
            ->and(UserRole::Accountant->getLabel())->toBe('Accountant')
            ->and(UserRole::Viewer->getLabel())->toBe('Viewer');
    });

    it('determines team management permission', function () {
        expect(UserRole::Admin->canManageTeam())->toBeTrue()
            ->and(UserRole::Accountant->canManageTeam())->toBeFalse()
            ->and(UserRole::Viewer->canManageTeam())->toBeFalse();
    });

    it('determines write permission', function () {
        expect(UserRole::Admin->canWrite())->toBeTrue()
            ->and(UserRole::Accountant->canWrite())->toBeTrue()
            ->and(UserRole::Viewer->canWrite())->toBeFalse();
    });
});

describe('User per-company roles', function () {
    it('resolves role for a specific company', function () {
        $user = User::factory()->admin()->create();
        $company = Company::factory()->create();
        $company->users()->attach($user, ['role' => UserRole::Accountant->value]);

        expect($user->roleForCompany($company))->toBe(UserRole::Accountant);
    });

    it('returns null when user is not in company', function () {
        $user = User::factory()->admin()->create();
        $company = Company::factory()->create();

        expect($user->roleForCompany($company))->toBeNull();
    });

    it('resolves current role from Filament tenant', function () {
        $user = asUser(role: UserRole::Accountant);

        expect($user->currentRole())->toBe(UserRole::Accountant);
    });

    it('isolates roles per company', function () {
        $user = User::factory()->admin()->create();
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $companyA->users()->attach($user, ['role' => UserRole::Admin->value]);
        $companyB->users()->attach($user, ['role' => UserRole::Viewer->value]);

        expect($user->roleForCompany($companyA))->toBe(UserRole::Admin)
            ->and($user->roleForCompany($companyB))->toBe(UserRole::Viewer);
    });

    it('returns null when no tenant is set', function () {
        $user = User::factory()->admin()->create();

        Filament::setTenant(null);

        expect($user->currentRole())->toBeNull();
    });
});
