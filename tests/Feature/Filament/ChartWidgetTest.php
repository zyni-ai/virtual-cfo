<?php

use App\Filament\Widgets\ImportsOverTimeChart;
use App\Filament\Widgets\MappingStatusChart;
use App\Filament\Widgets\MonthlyDebitCreditChart;
use App\Filament\Widgets\TopAccountHeadsChart;
use App\Models\AccountHead;
use App\Models\ImportedFile;
use App\Models\Transaction;

use function Pest\Livewire\livewire;

/**
 * Helper to call protected getData() on a ChartWidget via reflection.
 *
 * @return array<string, mixed>
 */
function getChartData(string $widgetClass): array
{
    $widget = new $widgetClass;
    $method = new ReflectionMethod($widget, 'getData');

    return $method->invoke($widget);
}

describe('ImportsOverTimeChart widget', function () {
    beforeEach(function () {
        asUser();
    });

    it('can render', function () {
        livewire(ImportsOverTimeChart::class)->assertSuccessful();
    });

    it('is a line chart', function () {
        $widget = new ImportsOverTimeChart;
        $method = new ReflectionMethod($widget, 'getType');

        expect($method->invoke($widget))->toBe('line');
    });

    it('returns chart data with 12 months of labels and one dataset', function () {
        ImportedFile::factory()->count(3)->create([
            'company_id' => tenant()->id,
            'created_at' => now(),
        ]);

        $data = getChartData(ImportsOverTimeChart::class);

        expect($data)
            ->toHaveKey('datasets')
            ->toHaveKey('labels')
            ->and($data['labels'])->toHaveCount(12)
            ->and($data['datasets'])->toHaveCount(1)
            ->and($data['datasets'][0])->toHaveKey('label')
            ->and($data['datasets'][0])->toHaveKey('data')
            ->and($data['datasets'][0]['data'])->toHaveCount(12);
    });

    it('counts imports grouped by month for last 12 months', function () {
        $company = tenant();

        ImportedFile::factory()->count(3)->create([
            'company_id' => $company->id,
            'created_at' => now(),
        ]);

        ImportedFile::factory()->count(2)->create([
            'company_id' => $company->id,
            'created_at' => now()->subMonth(),
        ]);

        $data = getChartData(ImportsOverTimeChart::class);

        // Last element = current month count
        $lastValue = end($data['datasets'][0]['data']);
        expect($lastValue)->toBe(3);

        // Second-to-last = previous month count
        $values = $data['datasets'][0]['data'];
        expect($values[count($values) - 2])->toBe(2);
    });
});

describe('MappingStatusChart widget', function () {
    beforeEach(function () {
        asUser();
    });

    it('can render', function () {
        livewire(MappingStatusChart::class)->assertSuccessful();
    });

    it('is a doughnut chart', function () {
        $widget = new MappingStatusChart;
        $method = new ReflectionMethod($widget, 'getType');

        expect($method->invoke($widget))->toBe('doughnut');
    });

    it('returns labels for all four mapping types', function () {
        $company = tenant();

        Transaction::factory()->unmapped()->count(5)->create(['company_id' => $company->id]);
        Transaction::factory()->mapped()->count(3)->create(['company_id' => $company->id]);
        Transaction::factory()->autoMapped()->count(2)->create(['company_id' => $company->id]);
        Transaction::factory()->aiMapped()->count(4)->create(['company_id' => $company->id]);

        $data = getChartData(MappingStatusChart::class);

        expect($data)
            ->toHaveKey('datasets')
            ->toHaveKey('labels')
            ->and($data['labels'])->toHaveCount(4)
            ->and($data['datasets'])->toHaveCount(1)
            ->and($data['datasets'][0]['data'])->toHaveCount(4);
    });

    it('counts transactions per mapping type', function () {
        $company = tenant();

        Transaction::factory()->unmapped()->count(5)->create(['company_id' => $company->id]);
        Transaction::factory()->mapped()->count(3)->create(['company_id' => $company->id]);
        Transaction::factory()->autoMapped()->count(2)->create(['company_id' => $company->id]);
        Transaction::factory()->aiMapped()->count(4)->create(['company_id' => $company->id]);

        $data = getChartData(MappingStatusChart::class);

        $labels = $data['labels'];
        $values = $data['datasets'][0]['data'];
        $map = array_combine($labels, $values);

        expect($map['Unmapped'])->toBe(5)
            ->and($map['Auto (Rule)'])->toBe(2)
            ->and($map['Manual'])->toBe(3)
            ->and($map['AI Matched'])->toBe(4);
    });
});

describe('TopAccountHeadsChart widget', function () {
    beforeEach(function () {
        asUser();
    });

    it('can render', function () {
        livewire(TopAccountHeadsChart::class)->assertSuccessful();
    });

    it('is a bar chart', function () {
        $widget = new TopAccountHeadsChart;
        $method = new ReflectionMethod($widget, 'getType');

        expect($method->invoke($widget))->toBe('bar');
    });

    it('returns top 10 account heads by transaction count', function () {
        $company = tenant();

        $heads = AccountHead::factory()->count(12)->create(['company_id' => $company->id]);

        // Assign decreasing transaction counts: 12, 11, 10, ..., 1
        $heads->each(function (AccountHead $head, int $index) use ($company) {
            Transaction::factory()
                ->count(12 - $index)
                ->mapped($head)
                ->create(['company_id' => $company->id]);
        });

        $data = getChartData(TopAccountHeadsChart::class);

        expect($data['labels'])->toHaveCount(10)
            ->and($data['datasets'][0]['data'])->toHaveCount(10)
            ->and($data['datasets'][0]['data'][0])->toBe(12)
            ->and($data['datasets'][0]['data'][9])->toBe(3);
    });

    it('handles fewer than 10 account heads', function () {
        $company = tenant();

        $head = AccountHead::factory()->create(['company_id' => $company->id]);
        Transaction::factory()->count(5)->mapped($head)->create(['company_id' => $company->id]);

        $data = getChartData(TopAccountHeadsChart::class);

        expect($data['labels'])->toHaveCount(1)
            ->and($data['datasets'][0]['data'])->toHaveCount(1)
            ->and($data['datasets'][0]['data'][0])->toBe(5);
    });
});

describe('MonthlyDebitCreditChart widget', function () {
    beforeEach(function () {
        asUser();
    });

    it('can render', function () {
        livewire(MonthlyDebitCreditChart::class)->assertSuccessful();
    });

    it('is a bar chart', function () {
        $widget = new MonthlyDebitCreditChart;
        $method = new ReflectionMethod($widget, 'getType');

        expect($method->invoke($widget))->toBe('bar');
    });

    it('returns two datasets for debits and credits', function () {
        $company = tenant();

        Transaction::factory()->debit(1000)->create([
            'company_id' => $company->id,
            'date' => now(),
        ]);
        Transaction::factory()->credit(2000)->create([
            'company_id' => $company->id,
            'date' => now(),
        ]);

        $data = getChartData(MonthlyDebitCreditChart::class);

        expect($data)
            ->toHaveKey('datasets')
            ->toHaveKey('labels')
            ->and($data['labels'])->toHaveCount(12)
            ->and($data['datasets'])->toHaveCount(2);

        $datasetLabels = array_column($data['datasets'], 'label');
        expect($datasetLabels)->toContain('Debits')
            ->toContain('Credits');
    });

    it('sums debit and credit amounts per month via Eloquent', function () {
        $company = tenant();

        // Current month: 2 debits and 1 credit
        Transaction::factory()->debit(1000.50)->create([
            'company_id' => $company->id,
            'date' => now(),
        ]);
        Transaction::factory()->debit(500.25)->create([
            'company_id' => $company->id,
            'date' => now(),
        ]);
        Transaction::factory()->credit(3000.75)->create([
            'company_id' => $company->id,
            'date' => now(),
        ]);

        // 2 months ago: 1 credit
        Transaction::factory()->credit(5000)->create([
            'company_id' => $company->id,
            'date' => now()->subMonths(2),
        ]);

        $data = getChartData(MonthlyDebitCreditChart::class);

        $debitDataset = collect($data['datasets'])->firstWhere('label', 'Debits');
        $creditDataset = collect($data['datasets'])->firstWhere('label', 'Credits');

        // Current month (last element)
        $lastDebit = end($debitDataset['data']);
        $lastCredit = end($creditDataset['data']);

        expect($lastDebit)->toBe(1500.75)
            ->and($lastCredit)->toBe(3000.75);

        // 2 months ago
        $idx = count($creditDataset['data']) - 3;
        expect($creditDataset['data'][$idx])->toBe(5000.0);
    });
});
