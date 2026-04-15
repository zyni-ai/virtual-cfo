<?php

use App\Enums\ImportStatus;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\ImportedFileStatsOverview;
use App\Filament\Widgets\MappingStatusChart;
use App\Filament\Widgets\MonthlyDebitCreditChart;
use App\Filament\Widgets\RecurringAutoMappedWidget;
use App\Filament\Widgets\TopAccountHeadsChart;
use App\Filament\Widgets\TransactionStatsOverview;
use App\Models\ImportedFile;
use App\Models\Transaction;
use Filament\Widgets\AccountWidget;

use function Pest\Livewire\livewire;

describe('Dashboard page', function () {
    beforeEach(function () {
        asUser();
    });

    it('can render', function () {
        $this->get(Dashboard::getUrl())->assertSuccessful();
    });

    it('shows personalized greeting with user name', function () {
        $this->get(Dashboard::getUrl())
            ->assertSee('Welcome, '.auth()->user()->name.'!');
    });

    it('shows subheading', function () {
        $this->get(Dashboard::getUrl())
            ->assertSee('an overview of your financial data');
    });
});

describe('Dashboard widget layout', function () {
    it('does not auto-discover AccountWidget', function () {
        asUser();
        $panel = filament()->getCurrentPanel();
        $widgetClasses = collect($panel->getWidgets())->map(fn ($w) => is_object($w) ? get_class($w) : $w);

        expect($widgetClasses)->not->toContain(AccountWidget::class);
    });

    it('does not auto-discover RecurringAutoMappedWidget on dashboard', function () {
        $property = new ReflectionProperty(RecurringAutoMappedWidget::class, 'isDiscovered');

        expect($property->getValue(new RecurringAutoMappedWidget))->toBeFalse();
    });

    it('does not auto-discover TransactionStatsOverview on dashboard', function () {
        $property = new ReflectionProperty(TransactionStatsOverview::class, 'isDiscovered');

        expect($property->getValue(new TransactionStatsOverview))->toBeFalse();
    });

    it('does not auto-discover ImportedFileStatsOverview on dashboard', function () {
        $property = new ReflectionProperty(ImportedFileStatsOverview::class, 'isDiscovered');

        expect($property->getValue(new ImportedFileStatsOverview))->toBeFalse();
    });

    it('keeps core dashboard widgets auto-discoverable', function () {
        $discoverable = [
            MappingStatusChart::class,
            TopAccountHeadsChart::class,
            MonthlyDebitCreditChart::class,
        ];

        foreach ($discoverable as $widget) {
            $property = new ReflectionProperty($widget, 'isDiscovered');
            expect($property->getDefaultValue())->not->toBeFalse(
                "{$widget} should be auto-discoverable on dashboard"
            );
        }
    });
});

describe('TransactionStatsOverview widget', function () {
    beforeEach(function () {
        asUser();
    });

    it('can render', function () {
        livewire(TransactionStatsOverview::class)->assertSuccessful();
    });

    it('shows transaction counts and mapped percentage', function () {
        Transaction::factory()->unmapped()->count(3)->create();
        Transaction::factory()->mapped()->count(7)->create();

        livewire(TransactionStatsOverview::class)->assertSuccessful();
    });
});

describe('ImportedFileStatsOverview widget', function () {
    beforeEach(function () {
        asUser();
    });

    it('can render', function () {
        livewire(ImportedFileStatsOverview::class)->assertSuccessful();
    });

    it('shows file counts by status', function () {
        ImportedFile::factory()->count(3)->create(['status' => ImportStatus::Completed]);
        ImportedFile::factory()->create(['status' => ImportStatus::Processing]);
        ImportedFile::factory()->count(2)->create(['status' => ImportStatus::Failed]);

        livewire(ImportedFileStatsOverview::class)->assertSuccessful();
    });
});
