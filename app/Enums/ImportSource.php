<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ImportSource: string implements HasColor, HasIcon, HasLabel
{
    case ManualUpload = 'manual_upload';
    case Email = 'email';
    case Zoho = 'zoho';
    case Api = 'api';

    public function getLabel(): string
    {
        return match ($this) {
            self::ManualUpload => 'Manual Upload',
            self::Email => 'Email',
            self::Zoho => 'Zoho',
            self::Api => 'API',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ManualUpload => 'gray',
            self::Email => 'info',
            self::Zoho => 'success',
            self::Api => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::ManualUpload => 'heroicon-m-arrow-up-tray',
            self::Email => 'heroicon-m-envelope',
            self::Zoho => 'heroicon-m-cloud-arrow-down',
            self::Api => 'heroicon-m-code-bracket',
        };
    }
}
