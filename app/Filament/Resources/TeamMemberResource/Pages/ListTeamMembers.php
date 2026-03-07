<?php

namespace App\Filament\Resources\TeamMemberResource\Pages;

use App\Filament\Resources\TeamMemberResource;
use App\Filament\Widgets\PendingInvitations;
use Filament\Resources\Pages\ListRecords;

class ListTeamMembers extends ListRecords
{
    protected static string $resource = TeamMemberResource::class;

    /** @return array<class-string> */
    protected function getFooterWidgets(): array
    {
        return [
            PendingInvitations::class,
        ];
    }
}
