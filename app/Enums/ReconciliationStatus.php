<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ReconciliationStatus: string implements HasColor, HasIcon, HasLabel
{
    case Unreconciled = 'unreconciled';
    case Matched = 'matched';
    case PartiallyMatched = 'partially_matched';
    case Flagged = 'flagged';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unreconciled => 'Unreconciled',
            self::Matched => 'Matched',
            self::PartiallyMatched => 'Partially Matched',
            self::Flagged => 'Flagged',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Unreconciled => 'gray',
            self::Matched => 'success',
            self::PartiallyMatched => 'warning',
            self::Flagged => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Unreconciled => 'heroicon-m-minus-circle',
            self::Matched => 'heroicon-m-check-circle',
            self::PartiallyMatched => 'heroicon-m-exclamation-circle',
            self::Flagged => 'heroicon-m-flag',
        };
    }
}
