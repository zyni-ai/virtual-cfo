<?php

use App\Filament\Resources\AccountHeadResource;
use App\Filament\Resources\AccountHeadResource\Pages\CreateAccountHead;
use App\Filament\Resources\AccountHeadResource\Pages\EditAccountHead;
use App\Filament\Resources\AccountHeadResource\Pages\ListAccountHeads;
use App\Models\AccountHead;

use function Pest\Livewire\livewire;

describe('AccountHeadResource', function () {
    beforeEach(function () {
        asUser();
    });

    it('can render the list page', function () {
        livewire(ListAccountHeads::class)->assertSuccessful();
    });

    it('can list account heads', function () {
        $heads = AccountHead::factory()->count(3)->create();

        livewire(ListAccountHeads::class)
            ->assertCanSeeTableRecords($heads);
    });

    it('can render the create page', function () {
        livewire(CreateAccountHead::class)->assertSuccessful();
    });

    it('can create an account head', function () {
        livewire(CreateAccountHead::class)
            ->fillForm([
                'name' => 'Test Account Head',
                'group_name' => 'Current Assets',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(AccountHead::where('name', 'Test Account Head')->exists())->toBeTrue();
    });

    it('validates required fields on create', function () {
        livewire(CreateAccountHead::class)
            ->fillForm([
                'name' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);
    });

    it('can render the edit page', function () {
        $head = AccountHead::factory()->create();

        livewire(EditAccountHead::class, ['record' => $head->getRouteKey()])
            ->assertSuccessful();
    });

    it('can update an account head', function () {
        $head = AccountHead::factory()->create(['name' => 'Old Name']);

        livewire(EditAccountHead::class, ['record' => $head->getRouteKey()])
            ->fillForm([
                'name' => 'Updated Name',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($head->fresh()->name)->toBe('Updated Name');
    });

    it('can delete an account head from the table', function () {
        $head = AccountHead::factory()->create();

        livewire(ListAccountHeads::class)
            ->callTableAction('delete', $head);

        expect(AccountHead::find($head->id))->toBeNull();
    });

    it('has correct navigation properties', function () {
        expect(AccountHeadResource::getNavigationLabel())->toBe('Account Heads')
            ->and(AccountHeadResource::getNavigationSort())->toBe(3);
    });
});
