<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum NavigationGroup: string implements HasLabel
{
    case AutomationRules = 'automation_rules';
    case Company = 'company';

    public function getLabel(): string
    {
        return match ($this) {
            self::AutomationRules => 'Automation Rules',
            self::Company => 'Company',
        };
    }
}
