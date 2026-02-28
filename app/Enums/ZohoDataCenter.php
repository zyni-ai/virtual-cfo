<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ZohoDataCenter: string implements HasLabel
{
    case India = 'in';
    case Us = 'us';
    case Eu = 'eu';
    case Australia = 'au';
    case Japan = 'jp';

    public function getLabel(): string
    {
        return match ($this) {
            self::India => 'India (.in)',
            self::Us => 'United States (.com)',
            self::Eu => 'Europe (.eu)',
            self::Australia => 'Australia (.com.au)',
            self::Japan => 'Japan (.jp)',
        };
    }

    public function accountsUrl(): string
    {
        return match ($this) {
            self::India => 'https://accounts.zoho.in',
            self::Us => 'https://accounts.zoho.com',
            self::Eu => 'https://accounts.zoho.eu',
            self::Australia => 'https://accounts.zoho.com.au',
            self::Japan => 'https://accounts.zoho.jp',
        };
    }

    public function apiUrl(): string
    {
        return match ($this) {
            self::India => 'https://www.zohoapis.in',
            self::Us => 'https://www.zohoapis.com',
            self::Eu => 'https://www.zohoapis.eu',
            self::Australia => 'https://www.zohoapis.com.au',
            self::Japan => 'https://www.zohoapis.jp',
        };
    }
}
