<?php

use App\Filament\Widgets\ExpenseBySourceWidget;
use App\Filament\Widgets\ExpenseSummaryTable;
use App\Filament\Widgets\TopMoversWidget;
use App\Models\AccountHead;
use App\Models\BankAccount;
use App\Models\CreditCard;
use App\Models\TransactionAggregate;

use function Pest\Livewire\livewire;

describe('ExpenseSummaryTable widget', function () {
    beforeEach(function () {
        asUser();
    });

    it('can render', function () {
        livewire(ExpenseSummaryTable::class)->assertSuccessful();
    });

    it('is not auto-discovered on dashboard', function () {
        $property = new ReflectionProperty(ExpenseSummaryTable::class, 'isDiscovered');

        expect($property->getValue(new ExpenseSummaryTable))->toBeFalse();
    });

    it('shows account head rows with totals', function () {
        $company = tenant();

        $head = AccountHead::factory()->create(['company_id' => $company->id, 'name' => 'Rent']);

        TransactionAggregate::factory()->create([
            'company_id' => $company->id,
            'account_head_id' => $head->id,
            'year_month' => now()->format('Y-m'),
            'total_debit' => 50000,
        ]);

        livewire(ExpenseSummaryTable::class)
            ->assertSee('Rent')
            ->assertSee('50,000');
    });
});

describe('TopMoversWidget', function () {
    beforeEach(function () {
        asUser();
    });

    it('can render', function () {
        livewire(TopMoversWidget::class)->assertSuccessful();
    });

    it('has sort order 7', function () {
        expect(TopMoversWidget::getSort())->toBe(7);
    });

    it('shows top movers with change descriptions', function () {
        $company = tenant();

        $head = AccountHead::factory()->create(['company_id' => $company->id, 'name' => 'Marketing']);

        TransactionAggregate::factory()->create([
            'company_id' => $company->id,
            'account_head_id' => $head->id,
            'year_month' => now()->subMonth()->format('Y-m'),
            'total_debit' => 10000,
        ]);
        TransactionAggregate::factory()->create([
            'company_id' => $company->id,
            'account_head_id' => $head->id,
            'year_month' => now()->format('Y-m'),
            'total_debit' => 20000,
        ]);

        livewire(TopMoversWidget::class)
            ->assertSee('Marketing');
    });
});

describe('ExpenseBySourceWidget', function () {
    beforeEach(function () {
        asUser();
    });

    it('can render', function () {
        livewire(ExpenseBySourceWidget::class)->assertSuccessful();
    });

    it('is a doughnut chart', function () {
        $widget = new ExpenseBySourceWidget;
        $method = new ReflectionMethod($widget, 'getType');

        expect($method->invoke($widget))->toBe('doughnut');
    });

    it('has sort order 8', function () {
        expect(ExpenseBySourceWidget::getSort())->toBe(8);
    });

    it('returns datasets grouped by source', function () {
        $company = tenant();

        $head = AccountHead::factory()->create(['company_id' => $company->id]);
        $bank = BankAccount::factory()->create(['company_id' => $company->id, 'name' => 'HDFC Current']);
        $card = CreditCard::factory()->create(['company_id' => $company->id, 'name' => 'ICICI Visa']);

        TransactionAggregate::factory()->create([
            'company_id' => $company->id,
            'account_head_id' => $head->id,
            'bank_account_id' => $bank->id,
            'year_month' => now()->format('Y-m'),
            'total_debit' => 50000,
        ]);
        TransactionAggregate::factory()->create([
            'company_id' => $company->id,
            'account_head_id' => $head->id,
            'credit_card_id' => $card->id,
            'year_month' => now()->format('Y-m'),
            'total_debit' => 20000,
        ]);

        $data = getChartData(ExpenseBySourceWidget::class);

        expect($data)
            ->toHaveKey('datasets')
            ->toHaveKey('labels')
            ->and($data['labels'])->toContain('HDFC Current')
            ->and($data['labels'])->toContain('ICICI Visa')
            ->and($data['datasets'][0]['data'])->toHaveCount(2);
    });
});
