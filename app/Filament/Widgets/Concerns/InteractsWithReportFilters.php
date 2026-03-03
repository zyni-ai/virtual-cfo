<?php

namespace App\Filament\Widgets\Concerns;

trait InteractsWithReportFilters
{
    /** @var array<int, string> */
    protected const CHART_COLORS = [
        '#3b82f6', '#ef4444', '#22c55e', '#f59e0b', '#8b5cf6',
        '#ec4899', '#06b6d4', '#84cc16', '#f97316', '#6366f1',
    ];

    /**
     * Build filters array from page filters, excluding date range.
     *
     * @return array<string, mixed>
     */
    protected function buildEntityFilters(): array
    {
        $pageFilters = $this->pageFilters ?? [];

        return collect(['bankAccountIds', 'creditCardIds', 'accountHeadIds'])
            ->filter(fn (string $key) => ! empty($pageFilters[$key]))
            ->mapWithKeys(fn (string $key) => [$key => $pageFilters[$key]])
            ->all();
    }

    /**
     * Build complete filters array from page filters including date range.
     *
     * @return array<string, mixed>
     */
    protected function buildAllFilters(): array
    {
        $pageFilters = $this->pageFilters ?? [];

        return collect(['dateFrom', 'dateUntil', 'bankAccountIds', 'creditCardIds', 'accountHeadIds'])
            ->filter(fn (string $key) => ! empty($pageFilters[$key]))
            ->mapWithKeys(fn (string $key) => [$key => $pageFilters[$key]])
            ->all();
    }
}
