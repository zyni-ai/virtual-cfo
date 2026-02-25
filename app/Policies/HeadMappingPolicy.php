<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\HeadMapping;
use App\Models\User;

class HeadMappingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, HeadMapping $headMapping): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, HeadMapping $headMapping): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function delete(User $user, HeadMapping $headMapping): bool
    {
        return $user->role === UserRole::Admin;
    }
}
