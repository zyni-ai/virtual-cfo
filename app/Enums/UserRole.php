<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case Admin = 'admin';
    case Accountant = 'accountant';
    case Viewer = 'viewer';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Accountant => 'Accountant',
            self::Viewer => 'Viewer',
        };
    }

    public function canManageTeam(): bool
    {
        return $this === self::Admin;
    }

    public function canWrite(): bool
    {
        return in_array($this, [self::Admin, self::Accountant]);
    }
}
