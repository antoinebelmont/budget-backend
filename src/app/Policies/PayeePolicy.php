<?php

namespace App\Policies;

use App\Models\Payee;
use App\Models\User;

class PayeePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Payee $payee): bool
    {
        return $user->id === $payee->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Payee $payee): bool
    {
        return $user->id === $payee->user_id;
    }

    public function delete(User $user, Payee $payee): bool
    {
        return $user->id === $payee->user_id;
    }

    public function restore(User $user, Payee $payee): bool
    {
        return $user->id === $payee->user_id;
    }

    public function forceDelete(User $user, Payee $payee): bool
    {
        return $user->id === $payee->user_id;
    }
}
