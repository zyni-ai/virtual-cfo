<?php

namespace App\Filament\Widgets;

use App\Enums\MappingType;
use App\Models\Transaction;
use Filament\Widgets\ChartWidget;

class MappingStatusChart extends ChartWidget
{
    protected ?string $heading = 'Mapping Status Distribution';

    protected static ?int $sort = 3;

    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        $counts = Transaction::query()
            ->selectRaw('mapping_type, count(*) as total')
            ->groupBy('mapping_type')
            ->pluck('total', 'mapping_type');

        $labels = [];
        $data = [];
        $colors = [];

        foreach (MappingType::cases() as $type) {
            $labels[] = $type->getLabel();
            $data[] = (int) ($counts->get($type->value, 0));
            $colors[] = match ($type) {
                MappingType::Unmapped => '#9ca3af',
                MappingType::Auto => '#3b82f6',
                MappingType::Manual => '#22c55e',
                MappingType::Ai => '#f59e0b',
            };
        }

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => $colors,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
