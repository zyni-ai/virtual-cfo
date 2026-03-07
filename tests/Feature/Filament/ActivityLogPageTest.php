<?php

use App\Enums\UserRole;
use App\Filament\Resources\ActivityLogResource;
use App\Filament\Resources\ActivityLogResource\Pages\ListActivityLog;
use App\Models\Company;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

describe('Activity Log Page', function () {
    describe('Access control', function () {
        it('renders for admin users', function () {
            asUser(role: UserRole::Admin);

            livewire(ListActivityLog::class)
                ->assertSuccessful();
        });

        it('denies access to accountant users', function () {
            asUser(User::factory()->accountant()->create(), UserRole::Accountant);

            livewire(ListActivityLog::class)
                ->assertForbidden();
        });

        it('denies access to viewer users', function () {
            asUser(User::factory()->viewer()->create(), UserRole::Viewer);

            livewire(ListActivityLog::class)
                ->assertForbidden();
        });
    });

    describe('Tenant scoping', function () {
        it('shows activity log entries from tenant users only', function () {
            $admin = asUser(role: UserRole::Admin);
            $company = tenant();

            $member = User::factory()->accountant()->create();
            $company->users()->attach($member, ['role' => UserRole::Accountant->value]);

            activity('test')
                ->causedBy($admin)
                ->log('tenant_action');

            $otherCompany = Company::factory()->create();
            $otherUser = User::factory()->viewer()->create();
            $otherCompany->users()->attach($otherUser, ['role' => UserRole::Viewer->value]);

            activity('test')
                ->causedBy($otherUser)
                ->log('other_tenant_action');

            livewire(ListActivityLog::class)
                ->assertCanSeeTableRecords(
                    Activity::where('causer_id', $admin->id)->get()
                )
                ->assertCanNotSeeTableRecords(
                    Activity::where('causer_id', $otherUser->id)->get()
                );
        });
    });

    describe('Filters', function () {
        it('filters by event type', function () {
            $admin = asUser(role: UserRole::Admin);

            // Clear any auto-logged activities from setup
            Activity::query()->delete();

            activity('test')
                ->causedBy($admin)
                ->event('created')
                ->log('created_something');

            activity('test')
                ->causedBy($admin)
                ->event('updated')
                ->log('updated_something');

            $createdRecords = Activity::where('event', 'created')->get();
            $updatedRecords = Activity::where('event', 'updated')->get();

            livewire(ListActivityLog::class)
                ->filterTable('event', 'created')
                ->assertCanSeeTableRecords($createdRecords)
                ->assertCanNotSeeTableRecords($updatedRecords);
        });

        it('filters by causer (user)', function () {
            $admin = asUser(role: UserRole::Admin);
            $company = tenant();

            $member = User::factory()->accountant()->create();
            $company->users()->attach($member, ['role' => UserRole::Accountant->value]);

            // Clear any auto-logged activities from setup
            Activity::query()->delete();

            activity('test')
                ->causedBy($admin)
                ->log('admin_action');

            activity('test')
                ->causedBy($member)
                ->log('member_action');

            livewire(ListActivityLog::class)
                ->filterTable('causer_id', $member->id)
                ->assertCanSeeTableRecords(
                    Activity::where('causer_id', $member->id)->get()
                )
                ->assertCanNotSeeTableRecords(
                    Activity::where('causer_id', $admin->id)->get()
                );
        });

        it('filters by subject type', function () {
            $admin = asUser(role: UserRole::Admin);
            $company = tenant();

            // Clear any auto-logged activities from setup
            Activity::query()->delete();

            activity('test')
                ->causedBy($admin)
                ->performedOn($company)
                ->log('company_action');

            activity('test')
                ->causedBy($admin)
                ->performedOn($admin)
                ->log('user_action');

            livewire(ListActivityLog::class)
                ->filterTable('subject_type', Company::class)
                ->assertCanSeeTableRecords(
                    Activity::where('subject_type', Company::class)->get()
                )
                ->assertCanNotSeeTableRecords(
                    Activity::where('subject_type', User::class)->get()
                );
        });
    });

    describe('Sensitive field masking', function () {
        it('masks sensitive fields in properties display', function () {
            asUser(role: UserRole::Admin);

            $properties = [
                'name' => 'Visible Name',
                'description' => 'Sensitive description',
                'account_number' => '1234567890',
                'debit' => '5000.00',
            ];

            $masked = ActivityLogResource::maskProperties($properties);

            expect($masked)->toContain('Visible Name')
                ->and($masked)->toContain('***')
                ->and($masked)->not->toContain('Sensitive description')
                ->and($masked)->not->toContain('1234567890')
                ->and($masked)->not->toContain('5000.00');
        });
    });

    describe('CSV Export', function () {
        it('has an export header action', function () {
            asUser(role: UserRole::Admin);

            livewire(ListActivityLog::class)
                ->assertTableActionExists('export');
        });
    });

    describe('Navigation', function () {
        it('is in the Company navigation group', function () {
            expect(ActivityLogResource::getNavigationGroup())->toBe(\App\Enums\NavigationGroup::Company);
        });
    });
});
