<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AccountType: string implements HasLabel
{
    case Current = 'current';
    case Savings = 'savings';
    case CreditCard = 'credit_card';

    public function getLabel(): string
    {
        return match ($this) {
            self::Current => 'Current',
            self::Savings => 'Savings',
            self::CreditCard => 'Credit Card',
        };
    }
}
