<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MappingType: string implements HasLabel, HasColor
{
    case Unmapped = 'unmapped';
    case Auto = 'auto';
    case Manual = 'manual';
    case Ai = 'ai';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unmapped => 'Unmapped',
            self::Auto => 'Auto (Rule)',
            self::Manual => 'Manual',
            self::Ai => 'AI Matched',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Unmapped => 'gray',
            self::Auto => 'info',
            self::Manual => 'success',
            self::Ai => 'warning',
        };
    }
}
