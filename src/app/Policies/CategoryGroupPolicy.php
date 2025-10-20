<?php

namespace App\Policies;

use App\Models\CategoryGroup;
use App\Models\User;

class CategoryGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CategoryGroup $categoryGroup): bool
    {
        return $user->id === $categoryGroup->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, CategoryGroup $categoryGroup): bool
    {
        return $user->id === $categoryGroup->user_id;
    }

    public function delete(User $user, CategoryGroup $categoryGroup): bool
    {
        return $user->id === $categoryGroup->user_id;
    }

    public function restore(User $user, CategoryGroup $categoryGroup): bool
    {
        return $user->id === $categoryGroup->user_id;
    }

    public function forceDelete(User $user, CategoryGroup $categoryGroup): bool
    {
        return $user->id === $categoryGroup->user_id;
    }

    public function reorder(User $user): bool
    {
        return true; // User can reorder their own groups (checked in controller)
    }
}
