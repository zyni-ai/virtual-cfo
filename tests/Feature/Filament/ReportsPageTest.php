<?php

use App\Filament\Pages\Reports;
use App\Filament\Widgets\AccountHeadComparisonChart;
use App\Filament\Widgets\AccountHeadTrendsChart;
use App\Filament\Widgets\SourceBreakdownChart;

use function Pest\Livewire\livewire;

describe('Reports page', function () {
    beforeEach(function () {
        asUser();
    });

    it('can render', function () {
        livewire(Reports::class)->assertSuccessful();
    });

    it('has header widgets', function () {
        $page = new Reports;
        $widgets = $page->getHeaderWidgets();

        expect($widgets)->toContain(AccountHeadTrendsChart::class)
            ->toContain(AccountHeadComparisonChart::class)
            ->toContain(SourceBreakdownChart::class);
    });

    it('has a navigation icon', function () {
        expect(Reports::getNavigationIcon())->toBe('heroicon-o-chart-bar-square');
    });
});
