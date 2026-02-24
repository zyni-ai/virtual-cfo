<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MatchType: string implements HasLabel
{
    case Contains = 'contains';
    case Exact = 'exact';
    case Regex = 'regex';

    public function getLabel(): string
    {
        return match ($this) {
            self::Contains => 'Contains',
            self::Exact => 'Exact Match',
            self::Regex => 'Regex',
        };
    }
}
