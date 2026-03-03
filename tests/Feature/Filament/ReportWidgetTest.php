<?php

use App\Filament\Widgets\AccountHeadComparisonChart;
use App\Filament\Widgets\AccountHeadTrendsChart;
use App\Filament\Widgets\SourceBreakdownChart;
use App\Models\AccountHead;
use App\Models\BankAccount;
use App\Models\CreditCard;
use App\Models\TransactionAggregate;

use function Pest\Livewire\livewire;

describe('AccountHeadTrendsChart widget', function () {
    beforeEach(function () {
        asUser();
    });

    it('can render', function () {
        livewire(AccountHeadTrendsChart::class)->assertSuccessful();
    });

    it('is a line chart', function () {
        $widget = new AccountHeadTrendsChart;
        $method = new ReflectionMethod($widget, 'getType');

        expect($method->invoke($widget))->toBe('line');
    });

    it('is not auto-discovered on dashboard', function () {
        $property = new ReflectionProperty(AccountHeadTrendsChart::class, 'isDiscovered');

        expect($property->getValue(new AccountHeadTrendsChart))->toBeFalse();
    });

    it('returns datasets for top account heads with monthly data', function () {
        $company = tenant();

        $head1 = AccountHead::factory()->create(['company_id' => $company->id, 'name' => 'Rent']);
        $head2 = AccountHead::factory()->create(['company_id' => $company->id, 'name' => 'Salaries']);

        TransactionAggregate::factory()->create([
            'company_id' => $company->id,
            'account_head_id' => $head1->id,
            'year_month' => now()->format('Y-m'),
            'total_debit' => 50000,
        ]);
        TransactionAggregate::factory()->create([
            'company_id' => $company->id,
            'account_head_id' => $head2->id,
            'year_month' => now()->format('Y-m'),
            'total_debit' => 80000,
        ]);
        TransactionAggregate::factory()->create([
            'company_id' => $company->id,
            'account_head_id' => $head1->id,
            'year_month' => now()->subMonth()->format('Y-m'),
            'total_debit' => 45000,
        ]);

        $data = getChartData(AccountHeadTrendsChart::class);

        expect($data)
            ->toHaveKey('datasets')
            ->toHaveKey('labels')
            ->and($data['datasets'])->toHaveCount(2)
            ->and($data['labels'])->not->toBeEmpty();
    });

    it('returns empty datasets when no aggregates exist', function () {
        $data = getChartData(AccountHeadTrendsChart::class);

        expect($data['datasets'])->toBeEmpty()
            ->and($data['labels'])->toBeEmpty();
    });
});

describe('AccountHeadComparisonChart widget', function () {
    beforeEach(function () {
        asUser();
    });

    it('can render', function () {
        livewire(AccountHeadComparisonChart::class)->assertSuccessful();
    });

    it('is a bar chart', function () {
        $widget = new AccountHeadComparisonChart;
        $method = new ReflectionMethod($widget, 'getType');

        expect($method->invoke($widget))->toBe('bar');
    });

    it('is not auto-discovered on dashboard', function () {
        $property = new ReflectionProperty(AccountHeadComparisonChart::class, 'isDiscovered');

        expect($property->getValue(new AccountHeadComparisonChart))->toBeFalse();
    });

    it('returns two datasets comparing current and previous month', function () {
        $company = tenant();

        $head = AccountHead::factory()->create(['company_id' => $company->id, 'name' => 'Office Supplies']);

        TransactionAggregate::factory()->create([
            'company_id' => $company->id,
            'account_head_id' => $head->id,
            'year_month' => now()->format('Y-m'),
            'total_debit' => 10000,
        ]);
        TransactionAggregate::factory()->create([
            'company_id' => $company->id,
            'account_head_id' => $head->id,
            'year_month' => now()->subMonth()->format('Y-m'),
            'total_debit' => 8000,
        ]);

        $data = getChartData(AccountHeadComparisonChart::class);

        expect($data)
            ->toHaveKey('datasets')
            ->toHaveKey('labels')
            ->and($data['datasets'])->toHaveCount(2)
            ->and($data['labels'])->toContain('Office Supplies');
    });
});

describe('SourceBreakdownChart widget', function () {
    beforeEach(function () {
        asUser();
    });

    it('can render', function () {
        livewire(SourceBreakdownChart::class)->assertSuccessful();
    });

    it('is a bar chart', function () {
        $widget = new SourceBreakdownChart;
        $method = new ReflectionMethod($widget, 'getType');

        expect($method->invoke($widget))->toBe('bar');
    });

    it('is not auto-discovered on dashboard', function () {
        $property = new ReflectionProperty(SourceBreakdownChart::class, 'isDiscovered');

        expect($property->getValue(new SourceBreakdownChart))->toBeFalse();
    });

    it('returns datasets grouped by source per head', function () {
        $company = tenant();

        $head = AccountHead::factory()->create(['company_id' => $company->id, 'name' => 'Travel']);
        $bank = BankAccount::factory()->create(['company_id' => $company->id, 'name' => 'HDFC Current']);
        $card = CreditCard::factory()->create(['company_id' => $company->id, 'name' => 'ICICI Visa']);

        TransactionAggregate::factory()->create([
            'company_id' => $company->id,
            'account_head_id' => $head->id,
            'bank_account_id' => $bank->id,
            'year_month' => now()->format('Y-m'),
            'total_debit' => 30000,
        ]);
        TransactionAggregate::factory()->create([
            'company_id' => $company->id,
            'account_head_id' => $head->id,
            'credit_card_id' => $card->id,
            'year_month' => now()->format('Y-m'),
            'total_debit' => 15000,
        ]);

        $data = getChartData(SourceBreakdownChart::class);

        expect($data)
            ->toHaveKey('datasets')
            ->toHaveKey('labels')
            ->and($data['labels'])->toContain('Travel')
            ->and($data['datasets'])->not->toBeEmpty();
    });
});
