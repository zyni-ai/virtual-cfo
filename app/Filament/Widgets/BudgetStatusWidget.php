<?php

namespace App\Filament\Widgets;

use App\Models\Company;
use App\Services\BudgetService;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BudgetStatusWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        /** @var Company|null $company */
        $company = Filament::getTenant();

        if (! $company) {
            return [];
        }

        $service = app(BudgetService::class);
        $statuses = $service->getBudgetStatuses($company);

        if ($statuses->isEmpty()) {
            return [
                Stat::make('Budgets', 'No budgets set')
                    ->description('Set budgets to track spending')
                    ->color('gray'),
            ];
        }

        return $statuses->take(4)->map(function (array $status) {
            $budget = $status['budget'];
            $headName = $budget->accountHead?->name ?? 'Unknown';
            $percentage = $status['percentage'];

            $color = match (true) {
                $percentage >= 100 => 'danger',
                $percentage >= 80 => 'warning',
                default => 'success',
            };

            $icon = match (true) {
                $percentage >= 100 => 'heroicon-m-exclamation-triangle',
                $percentage >= 80 => 'heroicon-m-exclamation-circle',
                default => 'heroicon-m-check-circle',
            };

            return Stat::make($headName, number_format($percentage, 0).'%')
                ->description(sprintf(
                    '₹%s / ₹%s',
                    number_format($status['actual'], 0),
                    number_format((float) $budget->amount, 0),
                ))
                ->descriptionIcon($icon)
                ->color($color);
        })->toArray();
    }
}
