<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithReportFilters;
use App\Services\ReportingService;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;

class AccountHeadTrendsChart extends ChartWidget
{
    use InteractsWithPageFilters;
    use InteractsWithReportFilters;

    protected ?string $heading = 'Account Head Trends';

    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        $service = app(ReportingService::class);

        $result = $service->monthlyTotalsByHead($this->buildAllFilters());

        if (empty($result['heads'])) {
            return ['datasets' => [], 'labels' => []];
        }

        $months = $result['months'];
        $labels = array_map(fn (string $ym) => Carbon::createFromFormat('Y-m', $ym)->format('M Y'), $months);

        $datasets = [];
        foreach ($result['heads'] as $index => $head) {
            $data = [];
            foreach ($months as $month) {
                $data[] = $head['data'][$month] ?? 0;
            }

            $datasets[] = [
                'label' => $head['name'],
                'data' => $data,
                'borderColor' => self::CHART_COLORS[$index % count(self::CHART_COLORS)],
                'fill' => false,
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
