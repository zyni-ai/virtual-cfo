<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum InboundEmailStatus: string implements HasColor, HasIcon, HasLabel
{
    case Processed = 'processed';
    case Rejected = 'rejected';
    case Duplicate = 'duplicate';
    case NoAttachments = 'no_attachments';

    public function getLabel(): string
    {
        return match ($this) {
            self::Processed => 'Processed',
            self::Rejected => 'Rejected',
            self::Duplicate => 'Duplicate',
            self::NoAttachments => 'No Attachments',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Processed => 'success',
            self::Rejected => 'danger',
            self::Duplicate => 'gray',
            self::NoAttachments => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Processed => 'heroicon-m-check-circle',
            self::Rejected => 'heroicon-m-x-circle',
            self::Duplicate => 'heroicon-m-document-duplicate',
            self::NoAttachments => 'heroicon-m-paper-clip',
        };
    }
}
