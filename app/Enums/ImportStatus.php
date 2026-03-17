<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ImportStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case NeedsPassword = 'needs_password';
    case Duplicate = 'duplicate';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Skipped => 'Skipped',
            self::NeedsPassword => 'Needs Password',
            self::Duplicate => 'Duplicate',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Processing => 'warning',
            self::Completed => 'success',
            self::Failed => 'danger',
            self::Skipped => 'info',
            self::NeedsPassword => 'warning',
            self::Duplicate => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Pending => 'heroicon-m-clock',
            self::Processing => 'heroicon-m-arrow-path',
            self::Completed => 'heroicon-m-check-circle',
            self::Failed => 'heroicon-m-x-circle',
            self::Skipped => 'heroicon-m-forward',
            self::NeedsPassword => 'heroicon-m-lock-closed',
            self::Duplicate => 'heroicon-m-document-duplicate',
        };
    }
}
