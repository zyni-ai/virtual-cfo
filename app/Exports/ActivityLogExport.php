<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Spatie\Activitylog\Models\Activity;

/** @implements WithMapping<Activity> */
class ActivityLogExport implements FromQuery, WithHeadings, WithMapping
{
    /**
     * @param  Collection<int, int>  $tenantUserIds
     */
    public function __construct(
        public Collection $tenantUserIds,
    ) {}

    /** @return Builder<Activity> */
    public function query(): Builder
    {
        return Activity::query()
            ->whereIn('causer_id', $this->tenantUserIds)
            ->with('causer')
            ->orderByDesc('created_at');
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return [
            'Date',
            'User',
            'Event',
            'Subject Type',
            'Subject ID',
            'Description',
            'Log Name',
        ];
    }

    /** @return array<int, mixed> */
    public function map(mixed $row): array
    {
        /** @var Activity $row */
        return [
            $row->created_at->format('d M Y H:i'),
            $row->causer?->name ?? 'System',
            $row->event,
            $row->subject_type ? Str::afterLast($row->subject_type, '\\') : '',
            $row->subject_id,
            $row->description,
            $row->log_name,
        ];
    }
}
