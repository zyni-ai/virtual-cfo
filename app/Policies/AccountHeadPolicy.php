<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AccountHead;
use App\Models\User;

class AccountHeadPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AccountHead $accountHead): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, AccountHead $accountHead): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function delete(User $user, AccountHead $accountHead): bool
    {
        return $user->role === UserRole::Admin;
    }
}
