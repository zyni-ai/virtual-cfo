<?php

use App\Enums\MatchType;
use App\Filament\Resources\HeadMappingResource\Pages\CreateHeadMapping;
use App\Filament\Resources\HeadMappingResource\Pages\EditHeadMapping;
use App\Filament\Resources\HeadMappingResource\Pages\ListHeadMappings;
use App\Models\AccountHead;
use App\Models\HeadMapping;

use function Pest\Livewire\livewire;

describe('HeadMappingResource', function () {
    beforeEach(function () {
        asUser();
    });

    it('can render the list page', function () {
        livewire(ListHeadMappings::class)->assertSuccessful();
    });

    it('can list head mappings', function () {
        $mappings = HeadMapping::factory()->count(3)->create();

        livewire(ListHeadMappings::class)
            ->assertCanSeeTableRecords($mappings);
    });

    it('can render the create page', function () {
        livewire(CreateHeadMapping::class)->assertSuccessful();
    });

    it('can create a head mapping', function () {
        $head = AccountHead::factory()->create();

        livewire(CreateHeadMapping::class)
            ->fillForm([
                'pattern' => 'SALARY',
                'match_type' => MatchType::Contains->value,
                'account_head_id' => $head->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(HeadMapping::where('pattern', 'SALARY')->exists())->toBeTrue();
    });

    it('sets created_by to current user', function () {
        $head = AccountHead::factory()->create();
        $user = asUser();

        livewire(CreateHeadMapping::class)
            ->fillForm([
                'pattern' => 'EMI',
                'match_type' => MatchType::Contains->value,
                'account_head_id' => $head->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $mapping = HeadMapping::where('pattern', 'EMI')->first();
        expect($mapping->created_by)->toBe($user->id);
    });

    it('validates required fields on create', function () {
        livewire(CreateHeadMapping::class)
            ->fillForm([
                'pattern' => '',
                'match_type' => '',
                'account_head_id' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['pattern', 'match_type', 'account_head_id']);
    });

    it('can render the edit page', function () {
        $mapping = HeadMapping::factory()->create();

        livewire(EditHeadMapping::class, ['record' => $mapping->getRouteKey()])
            ->assertSuccessful();
    });

    it('can update a head mapping', function () {
        $mapping = HeadMapping::factory()->create(['pattern' => 'OLD_PATTERN']);

        livewire(EditHeadMapping::class, ['record' => $mapping->getRouteKey()])
            ->fillForm([
                'pattern' => 'NEW_PATTERN',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($mapping->fresh()->pattern)->toBe('NEW_PATTERN');
    });

    it('can delete a head mapping from the table', function () {
        $mapping = HeadMapping::factory()->create();

        livewire(ListHeadMappings::class)
            ->callTableAction('delete', $mapping);

        expect(HeadMapping::find($mapping->id))->toBeNull();
    });
});
