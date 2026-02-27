<?php

namespace App\Filament\Widgets;

use App\Models\ImportedFile;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class ImportsOverTimeChart extends ChartWidget
{
    protected ?string $heading = 'Imports Over Time';

    protected static ?int $sort = 3;

    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        $months = collect(range(11, 0))->map(fn (int $i) => now()->subMonths($i)->startOfMonth());

        $counts = ImportedFile::query()
            ->where('created_at', '>=', $months->first())
            ->get()
            ->groupBy(fn (ImportedFile $file) => Carbon::parse($file->created_at)->format('Y-m'))
            ->map->count();

        $labels = $months->map(fn (Carbon $date) => $date->format('M Y'))->toArray();
        $data = $months->map(fn (Carbon $date) => $counts->get($date->format('Y-m'), 0))->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Imports',
                    'data' => $data,
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
