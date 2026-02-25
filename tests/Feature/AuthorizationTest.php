<?php

use App\Models\AccountHead;
use App\Models\User;
use App\Policies\AccountHeadPolicy;
use App\Policies\HeadMappingPolicy;
use App\Policies\ImportedFilePolicy;
use App\Policies\TransactionPolicy;

describe('Authorization Policies', function () {
    describe('Admin role', function () {
        it('can view resources', function () {
            $admin = User::factory()->admin()->create();
            $policy = new AccountHeadPolicy;

            expect($policy->viewAny($admin))->toBeTrue();
        });

        it('can create resources', function () {
            $admin = User::factory()->admin()->create();

            expect((new AccountHeadPolicy)->create($admin))->toBeTrue()
                ->and((new ImportedFilePolicy)->create($admin))->toBeTrue()
                ->and((new TransactionPolicy)->create($admin))->toBeTrue()
                ->and((new HeadMappingPolicy)->create($admin))->toBeTrue();
        });

        it('can delete resources', function () {
            $admin = User::factory()->admin()->create();
            $head = AccountHead::factory()->create();
            $policy = new AccountHeadPolicy;

            expect($policy->delete($admin, $head))->toBeTrue();
        });
    });

    describe('Viewer role', function () {
        it('can view resources', function () {
            $viewer = User::factory()->viewer()->create();
            $policy = new AccountHeadPolicy;

            expect($policy->viewAny($viewer))->toBeTrue()
                ->and($policy->view($viewer, AccountHead::factory()->create()))->toBeTrue();
        });

        it('cannot create resources', function () {
            $viewer = User::factory()->viewer()->create();

            expect((new AccountHeadPolicy)->create($viewer))->toBeFalse()
                ->and((new ImportedFilePolicy)->create($viewer))->toBeFalse()
                ->and((new TransactionPolicy)->create($viewer))->toBeFalse()
                ->and((new HeadMappingPolicy)->create($viewer))->toBeFalse();
        });

        it('cannot update resources', function () {
            $viewer = User::factory()->viewer()->create();
            $head = AccountHead::factory()->create();

            expect((new AccountHeadPolicy)->update($viewer, $head))->toBeFalse();
        });

        it('cannot delete resources', function () {
            $viewer = User::factory()->viewer()->create();
            $head = AccountHead::factory()->create();

            expect((new AccountHeadPolicy)->delete($viewer, $head))->toBeFalse();
        });
    });

    describe('Panel access', function () {
        it('allows users with a role to access the panel', function () {
            $admin = User::factory()->admin()->create();
            $viewer = User::factory()->viewer()->create();

            expect($admin->canAccessPanel(Filament\Panel::make()))->toBeTrue()
                ->and($viewer->canAccessPanel(Filament\Panel::make()))->toBeTrue();
        });
    });
});
