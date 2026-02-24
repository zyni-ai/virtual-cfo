<?php

namespace App\Filament\Resources\HeadMappingResource\Pages;

use App\Filament\Resources\HeadMappingResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateHeadMapping extends CreateRecord
{
    protected static string $resource = HeadMappingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();

        return $data;
    }
}
