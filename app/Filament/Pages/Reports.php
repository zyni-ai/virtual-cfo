<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AccountHeadComparisonChart;
use App\Filament\Widgets\AccountHeadTrendsChart;
use App\Filament\Widgets\ExpenseSummaryTable;
use App\Filament\Widgets\SourceBreakdownChart;
use App\Models\AccountHead;
use App\Models\BankAccount;
use App\Models\CreditCard;
use App\Services\ReportingService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class Reports extends Page
{
    use HasFiltersForm;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $title = 'Reports';

    protected static ?string $navigationLabel = 'Reports';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.reports';

    public function filtersForm(Schema $schema): Schema
    {
        $service = app(ReportingService::class);
        $months = $service->financialYearMonths();

        $monthOptions = $months->mapWithKeys(
            fn ($month) => [$month->format('Y-m') => $month->format('M Y')]
        )->all();

        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Select::make('dateFrom')
                            ->label('From')
                            ->options($monthOptions)
                            ->placeholder('Start of FY'),

                        Select::make('dateUntil')
                            ->label('Until')
                            ->options($monthOptions)
                            ->placeholder('End of FY'),

                        Select::make('bankAccountIds')
                            ->label('Bank Accounts')
                            ->multiple()
                            ->options(fn () => BankAccount::where('company_id', Filament::getTenant()->id)->pluck('name', 'id'))
                            ->placeholder('All'),

                        Select::make('creditCardIds')
                            ->label('Credit Cards')
                            ->multiple()
                            ->options(fn () => CreditCard::where('company_id', Filament::getTenant()->id)->pluck('name', 'id'))
                            ->placeholder('All'),

                        Select::make('accountHeadIds')
                            ->label('Account Heads')
                            ->multiple()
                            ->options(fn () => AccountHead::where('company_id', Filament::getTenant()->id)->pluck('name', 'id'))
                            ->placeholder('All'),
                    ])
                    ->columns(5),
            ]);
    }

    /**
     * @return array<class-string>
     */
    public function getHeaderWidgets(): array
    {
        return [
            AccountHeadTrendsChart::class,
            AccountHeadComparisonChart::class,
            SourceBreakdownChart::class,
        ];
    }

    /**
     * @return array<class-string>
     */
    public function getFooterWidgets(): array
    {
        return [
            ExpenseSummaryTable::class,
        ];
    }
}
