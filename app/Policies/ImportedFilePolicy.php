<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ImportedFile;
use App\Models\User;

class ImportedFilePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ImportedFile $importedFile): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, ImportedFile $importedFile): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function delete(User $user, ImportedFile $importedFile): bool
    {
        return $user->role === UserRole::Admin;
    }
}
