<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithReportFilters;
use App\Services\ReportingService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Carbon;

class ExpenseSummaryTable extends TableWidget
{
    use InteractsWithPageFilters;
    use InteractsWithReportFilters;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Expense Summary';

    public function table(Table $table): Table
    {
        $service = app(ReportingService::class);
        $result = $service->expenseSummary($this->buildAllFilters());

        $columns = [
            TextColumn::make('head_name')
                ->label('Account Head')
                ->weight('bold'),
        ];

        foreach ($result['months'] as $month) {
            $label = Carbon::createFromFormat('Y-m', $month)->format('M Y');
            $columns[] = TextColumn::make("monthly.{$month}")
                ->label($label)
                ->numeric(decimalPlaces: 2)
                ->default(0);
        }

        $columns[] = TextColumn::make('row_total')
            ->label('Total')
            ->numeric(decimalPlaces: 2)
            ->weight('bold');

        $columns[] = TextColumn::make('percent_of_total')
            ->label('%')
            ->suffix('%')
            ->numeric(decimalPlaces: 1);

        $rows = $result['rows'];

        return $table
            ->records(fn () => collect($rows))
            ->columns($columns)
            ->paginated(false);
    }
}
