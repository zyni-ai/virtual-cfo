<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum MatchStatus: string implements HasColor, HasIcon, HasLabel
{
    case Suggested = 'suggested';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Suggested => 'Suggested',
            self::Confirmed => 'Confirmed',
            self::Rejected => 'Rejected',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Suggested => 'warning',
            self::Confirmed => 'success',
            self::Rejected => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Suggested => 'heroicon-m-light-bulb',
            self::Confirmed => 'heroicon-m-check-circle',
            self::Rejected => 'heroicon-m-x-circle',
        };
    }
}
