<?php

use App\Enums\PeriodType;
use App\Filament\Resources\BudgetResource;
use App\Filament\Resources\BudgetResource\Pages\ManageBudgets;
use App\Models\AccountHead;
use App\Models\Budget;

use function Pest\Livewire\livewire;

describe('BudgetResource', function () {
    beforeEach(function () {
        asUser();
        $this->company = tenant();
    });

    it('can render the list page', function () {
        livewire(ManageBudgets::class)->assertSuccessful();
    });

    it('can list budgets', function () {
        $budgets = Budget::factory()
            ->count(3)
            ->for($this->company)
            ->create();

        livewire(ManageBudgets::class)
            ->assertCanSeeTableRecords($budgets);
    });

    it('can create a budget', function () {
        $head = AccountHead::factory()->for($this->company)->create();

        livewire(ManageBudgets::class)
            ->callAction('create', data: [
                'account_head_id' => $head->id,
                'period_type' => PeriodType::Monthly->value,
                'amount' => 150000,
                'year_month' => '2026-03',
                'financial_year' => '2025-26',
                'is_active' => true,
            ]);

        expect(Budget::where('account_head_id', $head->id)->exists())->toBeTrue();
    });

    it('can edit a budget', function () {
        $budget = Budget::factory()->for($this->company)->create([
            'amount' => 100000,
        ]);

        livewire(ManageBudgets::class)
            ->callTableAction('edit', $budget, data: [
                'amount' => 200000,
            ]);

        $budget->refresh();
        expect((float) $budget->amount)->toBe(200000.0);
    });

    it('can delete a budget', function () {
        $budget = Budget::factory()->for($this->company)->create();

        livewire(ManageBudgets::class)
            ->callTableAction('delete', $budget);

        expect(Budget::find($budget->id))->toBeNull();
    });

    it('shows navigation badge with active budget count', function () {
        Budget::factory()->count(3)->for($this->company)->create();
        Budget::factory()->for($this->company)->inactive()->create();

        expect(BudgetResource::getNavigationBadge())->toBe('3');
    });
});
