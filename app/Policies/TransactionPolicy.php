<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, Transaction $transaction): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $user->role === UserRole::Admin;
    }
}
