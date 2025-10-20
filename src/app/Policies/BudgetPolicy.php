<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class BudgetPolicy
{
    /**
     * Determine whether the user can view the budget.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view a specific month's budget.
     */
    public function view(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update category budgets.
     */
    public function updateCategoryBudget(User $user, Category $category): bool
    {
        return $user->id === $category->user_id;
    }

    /**
     * Determine whether the user can create budget allocations.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update budget allocations.
     */
    public function update(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can move money between categories.
     */
    public function moveMoneyBetweenCategories(User $user, Category $fromCategory, Category $toCategory): bool
    {
        return $user->id === $fromCategory->user_id && $user->id === $toCategory->user_id;
    }

    /**
     * Determine whether the user can reset budget for a category.
     */
    public function resetCategoryBudget(User $user, Category $category): bool
    {
        return $user->id === $category->user_id;
    }

    /**
     * Determine whether the user can copy budget from previous month.
     */
    public function copyFromPreviousMonth(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can set budget targets.
     */
    public function setBudgetTargets(User $user): bool
    {
        return true;
    }
}
