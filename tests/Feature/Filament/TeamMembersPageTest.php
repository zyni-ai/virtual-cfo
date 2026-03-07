<?php

use App\Enums\UserRole;
use App\Filament\Resources\TeamMemberResource\Pages\ListTeamMembers;
use App\Models\Company;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

describe('Team Members Page', function () {
    describe('Access control', function () {
        it('renders for admin users', function () {
            asUser(role: UserRole::Admin);

            livewire(ListTeamMembers::class)
                ->assertSuccessful();
        });

        it('denies access to accountant users', function () {
            asUser(User::factory()->accountant()->create(), UserRole::Accountant);

            livewire(ListTeamMembers::class)
                ->assertForbidden();
        });

        it('denies access to viewer users', function () {
            asUser(User::factory()->viewer()->create(), UserRole::Viewer);

            livewire(ListTeamMembers::class)
                ->assertForbidden();
        });
    });

    describe('Team members table', function () {
        it('shows team members in the table', function () {
            $admin = asUser(role: UserRole::Admin);
            $company = tenant();

            $member = User::factory()->accountant()->create();
            $company->users()->attach($member, ['role' => UserRole::Accountant->value]);

            livewire(ListTeamMembers::class)
                ->assertCanSeeTableRecords([$admin, $member]);
        });

        it('does not show users from other tenants', function () {
            asUser(role: UserRole::Admin);

            $otherCompany = Company::factory()->create();
            $otherUser = User::factory()->viewer()->create();
            $otherCompany->users()->attach($otherUser, ['role' => UserRole::Viewer->value]);

            livewire(ListTeamMembers::class)
                ->assertCanNotSeeTableRecords([$otherUser]);
        });
    });

    describe('Change role action', function () {
        it('admin can change another user role', function () {
            asUser(role: UserRole::Admin);
            $company = tenant();

            $member = User::factory()->viewer()->create();
            $company->users()->attach($member, ['role' => UserRole::Viewer->value]);

            livewire(ListTeamMembers::class)
                ->callTableAction('change_role', $member, [
                    'role' => UserRole::Accountant->value,
                ])
                ->assertNotified('Role updated');

            $pivotRole = $company->users()->where('user_id', $member->id)->first()->pivot->role;
            expect($pivotRole)->toBe(UserRole::Accountant->value);
        });

        it('logs activity when role is changed', function () {
            asUser(role: UserRole::Admin);
            $company = tenant();

            $member = User::factory()->viewer()->create();
            $company->users()->attach($member, ['role' => UserRole::Viewer->value]);

            livewire(ListTeamMembers::class)
                ->callTableAction('change_role', $member, [
                    'role' => UserRole::Accountant->value,
                ]);

            $activity = Activity::where('log_name', 'team')
                ->where('description', 'role_changed')
                ->latest()
                ->first();

            expect($activity)->not->toBeNull()
                ->and($activity->properties['old_role'])->toBe('viewer')
                ->and($activity->properties['new_role'])->toBe('accountant');
        });

        it('cannot change own role', function () {
            $admin = asUser(role: UserRole::Admin);

            livewire(ListTeamMembers::class)
                ->assertTableActionHidden('change_role', $admin);
        });
    });

    describe('Remove member action', function () {
        it('admin can remove another user from company', function () {
            asUser(role: UserRole::Admin);
            $company = tenant();

            $member = User::factory()->viewer()->create();
            $company->users()->attach($member, ['role' => UserRole::Viewer->value]);

            livewire(ListTeamMembers::class)
                ->callTableAction('remove', $member)
                ->assertNotified('Member removed');

            expect($company->users()->where('user_id', $member->id)->exists())->toBeFalse();
        });

        it('logs activity when member is removed', function () {
            asUser(role: UserRole::Admin);
            $company = tenant();

            $member = User::factory()->viewer()->create(['name' => 'John Doe']);
            $company->users()->attach($member, ['role' => UserRole::Viewer->value]);

            livewire(ListTeamMembers::class)
                ->callTableAction('remove', $member);

            $activity = Activity::where('log_name', 'team')
                ->where('description', 'member_removed')
                ->latest()
                ->first();

            expect($activity)->not->toBeNull()
                ->and($activity->properties['email'])->toBe($member->email);
        });

        it('cannot remove self', function () {
            $admin = asUser(role: UserRole::Admin);

            livewire(ListTeamMembers::class)
                ->assertTableActionHidden('remove', $admin);
        });
    });
});
