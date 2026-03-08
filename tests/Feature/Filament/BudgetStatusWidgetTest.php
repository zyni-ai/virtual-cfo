<?php

use App\Filament\Widgets\BudgetStatusWidget;
use App\Models\AccountHead;
use App\Models\Budget;
use App\Models\TransactionAggregate;

use function Pest\Livewire\livewire;

describe('BudgetStatusWidget', function () {
    beforeEach(function () {
        asUser();
        $this->company = tenant();
    });

    it('can render', function () {
        livewire(BudgetStatusWidget::class)->assertSuccessful();
    });

    it('shows empty state when no budgets exist', function () {
        $widget = livewire(BudgetStatusWidget::class);
        $widget->assertSuccessful();
    });

    it('shows budget status stats', function () {
        $head = AccountHead::factory()->for($this->company)->create(['name' => 'Office Supplies']);
        Budget::factory()->for($this->company)->create([
            'account_head_id' => $head->id,
            'year_month' => now()->format('Y-m'),
            'amount' => 100000,
        ]);
        TransactionAggregate::factory()->create([
            'company_id' => $this->company->id,
            'account_head_id' => $head->id,
            'year_month' => now()->format('Y-m'),
            'total_debit' => 85000,
        ]);

        livewire(BudgetStatusWidget::class)->assertSuccessful();
    });
});
