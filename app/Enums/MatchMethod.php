<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MatchMethod: string implements HasLabel
{
    case Amount = 'amount';
    case AmountDate = 'amount_date';
    case AmountDateParty = 'amount_date_party';
    case Ai = 'ai';
    case Manual = 'manual';

    public function getLabel(): string
    {
        return match ($this) {
            self::Amount => 'Amount Match',
            self::AmountDate => 'Amount + Date',
            self::AmountDateParty => 'Amount + Date + Party',
            self::Ai => 'AI Matched',
            self::Manual => 'Manual',
        };
    }
}
