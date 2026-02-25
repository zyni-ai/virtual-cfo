<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;

class CompanySettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationLabel = 'Company Settings';

    protected static ?string $title = 'Company Settings';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.pages.company-settings';

    public function getViewData(): array
    {
        return [
            'company' => config('company'),
        ];
    }
}
