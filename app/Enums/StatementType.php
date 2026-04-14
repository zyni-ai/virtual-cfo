<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum StatementType: string implements HasLabel
{
    case Bank = 'bank';
    case CreditCard = 'credit_card';
    case Invoice = 'invoice';
    case SalesInvoice = 'sales_invoice';

    public function getLabel(): string
    {
        return match ($this) {
            self::Bank => 'Bank Statement',
            self::CreditCard => 'Credit Card Statement',
            self::Invoice => 'Invoice',
            self::SalesInvoice => 'Sales Invoice',
        };
    }
}
