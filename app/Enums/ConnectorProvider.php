<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ConnectorProvider: string implements HasLabel
{
    case Zoho = 'zoho';

    public function getLabel(): string
    {
        return match ($this) {
            self::Zoho => 'Zoho Invoice',
        };
    }
}
