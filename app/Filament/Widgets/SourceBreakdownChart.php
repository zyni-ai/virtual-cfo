<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithReportFilters;
use App\Services\ReportingService;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class SourceBreakdownChart extends ChartWidget
{
    use InteractsWithPageFilters;
    use InteractsWithReportFilters;

    protected ?string $heading = 'Expense by Source';

    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        $service = app(ReportingService::class);

        $result = $service->sourceBreakdown($this->buildAllFilters());

        if (empty($result)) {
            return ['datasets' => [], 'labels' => []];
        }

        $labels = array_column($result, 'head_name');

        $sourceNames = [];
        $sourceIndex = [];
        foreach ($result as $headIdx => $head) {
            foreach ($head['sources'] as $source) {
                $name = $source['source_name'];
                $sourceNames[$name] = true;
                $sourceIndex[$headIdx][$name] = ($sourceIndex[$headIdx][$name] ?? 0) + $source['total_debit'];
            }
        }
        $sourceNames = array_keys($sourceNames);

        $datasets = [];
        foreach ($sourceNames as $index => $sourceName) {
            $data = [];
            foreach ($result as $headIdx => $head) {
                $data[] = $sourceIndex[$headIdx][$sourceName] ?? 0;
            }

            $datasets[] = [
                'label' => $sourceName,
                'data' => $data,
                'backgroundColor' => self::CHART_COLORS[$index % count(self::CHART_COLORS)],
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => ['stacked' => true],
                'y' => ['stacked' => true],
            ],
        ];
    }
}
