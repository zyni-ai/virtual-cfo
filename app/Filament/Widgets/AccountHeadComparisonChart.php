<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithReportFilters;
use App\Services\ReportingService;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;

class AccountHeadComparisonChart extends ChartWidget
{
    use InteractsWithPageFilters;
    use InteractsWithReportFilters;

    protected ?string $heading = 'Period Comparison';

    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = null;

    public ?string $filter = 'month';

    protected function getFilters(): ?array
    {
        return [
            'month' => 'Month-over-Month',
            'quarter' => 'Quarter-over-Quarter',
            'year' => 'Year-over-Year',
        ];
    }

    protected function getData(): array
    {
        $service = app(ReportingService::class);

        [$periodA, $periodB] = $this->buildPeriods();

        $result = $service->periodComparison($periodA, $periodB, $this->buildEntityFilters());

        if (empty($result)) {
            return ['datasets' => [], 'labels' => []];
        }

        $labels = array_column($result, 'head_name');
        $periodAData = array_column($result, 'period_a_total');
        $periodBData = array_column($result, 'period_b_total');

        return [
            'datasets' => [
                [
                    'label' => $this->getPeriodLabel($periodA),
                    'data' => $periodAData,
                    'backgroundColor' => '#94a3b8',
                ],
                [
                    'label' => $this->getPeriodLabel($periodB),
                    'data' => $periodBData,
                    'backgroundColor' => '#3b82f6',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function buildPeriods(): array
    {
        $now = now();

        return match ($this->filter) {
            'quarter' => [
                $this->getQuarterMonths($now->copy()->subQuarter()),
                $this->getQuarterMonths($now),
            ],
            'year' => [
                $this->getYearMonths($now->copy()->subYear()),
                $this->getYearMonths($now),
            ],
            default => [
                [$now->copy()->subMonth()->format('Y-m')],
                [$now->format('Y-m')],
            ],
        };
    }

    /**
     * @return array<int, string>
     */
    private function getQuarterMonths(Carbon $ref): array
    {
        $start = $ref->copy()->startOfQuarter();

        return collect(range(0, 2))->map(
            fn (int $i) => $start->copy()->addMonths($i)->format('Y-m')
        )->all();
    }

    /**
     * @return array<int, string>
     */
    private function getYearMonths(Carbon $ref): array
    {
        return app(ReportingService::class)
            ->financialYearMonths($ref)
            ->map(fn (Carbon $m) => $m->format('Y-m'))
            ->all();
    }

    /**
     * @param  array<int, string>  $months
     */
    private function getPeriodLabel(array $months): string
    {
        if (count($months) === 1) {
            return Carbon::createFromFormat('Y-m', $months[0])->format('M Y');
        }

        $first = Carbon::createFromFormat('Y-m', $months[0])->format('M Y');
        $last = Carbon::createFromFormat('Y-m', end($months))->format('M Y');

        return "{$first} – {$last}";
    }
}
