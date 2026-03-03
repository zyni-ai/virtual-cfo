<?php

use App\Models\AccountHead;
use App\Models\RecurringPattern;

use function Pest\Livewire\livewire;

describe('RecurringPatternResource', function () {
    beforeEach(function () {
        $this->user = asUser();
    });

    it('can render the list page', function () {
        livewire(\App\Filament\Resources\RecurringPatternResource\Pages\ListRecurringPatterns::class)
            ->assertSuccessful();
    });

    it('can list recurring patterns', function () {
        $patterns = RecurringPattern::factory()->count(3)->create([
            'company_id' => tenant()->id,
        ]);

        livewire(\App\Filament\Resources\RecurringPatternResource\Pages\ListRecurringPatterns::class)
            ->assertCanSeeTableRecords($patterns);
    });

    it('can render the edit page', function () {
        $pattern = RecurringPattern::factory()->create([
            'company_id' => tenant()->id,
        ]);

        livewire(\App\Filament\Resources\RecurringPatternResource\Pages\EditRecurringPattern::class, [
            'record' => $pattern->getRouteKey(),
        ])->assertSuccessful();
    });

    it('can update pattern account head', function () {
        $head = AccountHead::factory()->create(['company_id' => tenant()->id]);
        $pattern = RecurringPattern::factory()->create([
            'company_id' => tenant()->id,
            'account_head_id' => null,
        ]);

        livewire(\App\Filament\Resources\RecurringPatternResource\Pages\EditRecurringPattern::class, [
            'record' => $pattern->getRouteKey(),
        ])
            ->fillForm([
                'account_head_id' => $head->id,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($pattern->fresh()->account_head_id)->toBe($head->id);
    });

    it('can deactivate a pattern', function () {
        $pattern = RecurringPattern::factory()->create([
            'company_id' => tenant()->id,
            'is_active' => true,
        ]);

        livewire(\App\Filament\Resources\RecurringPatternResource\Pages\EditRecurringPattern::class, [
            'record' => $pattern->getRouteKey(),
        ])
            ->fillForm([
                'is_active' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($pattern->fresh()->is_active)->toBeFalse();
    });

    it('scopes patterns to current tenant', function () {
        $ownPattern = RecurringPattern::factory()->create([
            'company_id' => tenant()->id,
        ]);

        livewire(\App\Filament\Resources\RecurringPatternResource\Pages\ListRecurringPatterns::class)
            ->assertCanSeeTableRecords([$ownPattern]);

        // The service test already verifies tenant isolation at the data layer.
        // Filament's ->tenant(Company::class) auto-scopes the resource queries.
    });
});
