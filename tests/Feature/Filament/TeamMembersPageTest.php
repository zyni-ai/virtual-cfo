<?php

use App\Enums\UserRole;
use App\Filament\Pages\TeamMembers;
use App\Models\Company;
use App\Models\User;

use function Pest\Livewire\livewire;

describe('Team Members Page', function () {
    it('renders for admin users', function () {
        asUser(role: UserRole::Admin);

        livewire(TeamMembers::class)
            ->assertSuccessful();
    });

    it('shows team members in the table', function () {
        $admin = asUser(role: UserRole::Admin);
        $company = tenant();

        $member = User::factory()->accountant()->create();
        $company->users()->attach($member, ['role' => UserRole::Accountant->value]);

        livewire(TeamMembers::class)
            ->assertCanSeeTableRecords([$admin, $member]);
    });

    it('does not show users from other tenants', function () {
        asUser(role: UserRole::Admin);

        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->viewer()->create();
        $otherCompany->users()->attach($otherUser, ['role' => UserRole::Viewer->value]);

        livewire(TeamMembers::class)
            ->assertCanNotSeeTableRecords([$otherUser]);
    });

    it('denies access to accountant users', function () {
        asUser(User::factory()->accountant()->create(), UserRole::Accountant);

        livewire(TeamMembers::class)
            ->assertForbidden();
    });

    it('denies access to viewer users', function () {
        asUser(User::factory()->viewer()->create(), UserRole::Viewer);

        livewire(TeamMembers::class)
            ->assertForbidden();
    });
});
