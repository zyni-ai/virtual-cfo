<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum NavigationGroup: string implements HasLabel
{
    case Configuration = 'configuration';
    case Company = 'company';

    public function getLabel(): string
    {
        return match ($this) {
            self::Configuration => 'Configuration',
            self::Company => 'Company',
        };
    }
}
