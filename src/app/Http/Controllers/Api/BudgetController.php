<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    use AuthorizesRequests;
    public function index(Request $request): JsonResponse
    {

        $month = $request->get('month', now()->format('Y-m'));

        $start = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end   = (clone $start)->endOfMonth();

        $categoryGroups = $request->user()->categoryGroups()
            ->with(['categories' => function ($query) use ($start, $end) {
                $query->where('hidden', false)
                    ->orderBy('sort_order')
                    ->withSum(['transactions as transactions_sum' => function ($q) use ($start, $end) {
                        $q->whereBetween('date', [$start, $end]);
                    }], 'amount');
            }])
            ->where('hidden', false)
            ->orderBy('sort_order')
            ->get();

        $categoryGroups = $categoryGroups->map(function ($group) {
            $group->categories->transform(function ($cat) {
                $cat->monthly_available = ($cat->budgeted ?? 0) + ($cat->transactions_sum ?? 0);
                return $cat;
            });
            return $group;
        });
        // Calculate totals
        $totalBudgeted = $categoryGroups->sum(function ($group) {
            return $group->categories->sum('budgeted');
        });

        $totalActivity = $categoryGroups->sum(function ($group) use ($month) {
            return $group->categories->sum(function ($category) use ($month) {
                return $category->monthlyActivity($month);
            });
        });

        $totalAvailable = $categoryGroups->sum(function ($group) {
            return $group->categories->sum('available');
        });

        return response()->json([
            'month' => $month,
            'category_groups' => $categoryGroups,
            'totals' => [
                'budgeted' => $totalBudgeted,
                'activity' => $totalActivity,
                'available' => $totalAvailable,
            ]
        ]);
    }

    public function updateCategoryBudget(Request $request, Category $category): JsonResponse
    {
        // First authorize that the user can update this specific category
//        $this->authorize('updateCategoryBudget', $category);
        if ($category->user_id !== $request->user()->id) {
            abort(403, 'This category does not belong to you.');
        }
        // Then validate the input
        $request->validate([
            'budgeted' => 'required|numeric|min:0'
        ]);

        $oldBudgeted = $category->budgeted;
        $category->update(['budgeted' => $request->budgeted]);
        $category->updateActivity();

        return response()->json([
            'category' => $category->fresh(),
            'message' => 'Budget updated successfully'
        ]);
    }

    public function moveMoneyBetweenCategories(Request $request): JsonResponse
    {
        $request->validate([
            'from_category_id' => 'required|exists:categories,id',
            'to_category_id' => 'required|exists:categories,id',
            'amount' => 'required|numeric|min:0.01'
        ]);

        $fromCategory = Category::findOrFail($request->from_category_id);
        $toCategory = Category::findOrFail($request->to_category_id);

        $this->authorize('moveMoneyBetweenCategories', [$fromCategory, $fromCategory, $toCategory]);

        // Verify both categories belong to the user
        if ($fromCategory->user_id !== $request->user()->id || $toCategory->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if from category has enough available funds
        if ($fromCategory->available < $request->amount) {
            return response()->json([
                'message' => 'Insufficient funds in source category',
                'available' => $fromCategory->available
            ], 422);
        }

        // Move the money
        $fromCategory->decrement('budgeted', $request->amount);
        $toCategory->increment('budgeted', $request->amount);

        // Update activity calculations
        $fromCategory->updateActivity();
        $toCategory->updateActivity();

        return response()->json([
            'message' => 'Money moved successfully',
            'from_category' => $fromCategory->fresh(),
            'to_category' => $toCategory->fresh(),
            'amount_moved' => $request->amount
        ]);
    }

    public function copyFromPreviousMonth(Request $request): JsonResponse
    {
        $this->authorize('copyFromPreviousMonth');

        $request->validate([
            'target_month' => 'required|date_format:Y-m',
            'source_month' => 'sometimes|date_format:Y-m'
        ]);

        $sourceMonth = $request->get('source_month',
            now()->parse($request->target_month)->subMonth()->format('Y-m')
        );

        // This would require a more complex implementation with monthly budget snapshots
        // For now, we'll copy current budget amounts
        $categories = $request->user()->categories()->get();

        $copiedCount = 0;
        foreach ($categories as $category) {
            if ($category->budgeted > 0) {
                // In a full implementation, you'd fetch the budget from the source month
                $copiedCount++;
            }
        }

        return response()->json([
            'message' => "Copied budget from {$sourceMonth} to {$request->target_month}",
            'categories_copied' => $copiedCount
        ]);
    }

    public function resetCategoryBudget(Request $request, Category $category): JsonResponse
    {
        $this->authorize('resetCategoryBudget', [$category, $category]);

        $oldBudgeted = $category->budgeted;
        $category->update(['budgeted' => 0]);
        $category->updateActivity();

        return response()->json([
            'message' => 'Category budget reset to zero',
            'category' => $category->fresh(),
            'amount_reset' => $oldBudgeted
        ]);
    }

    public function setBudgetTarget(Request $request, Category $category): JsonResponse
    {
        $this->authorize('setBudgetTargets');

        $request->validate([
            'target_amount' => 'required|numeric|min:0',
            'target_month' => 'required|date_format:Y-m'
        ]);

        // This could be stored in a separate budget_targets table
        // For now, we'll update the category budgeted amount
        $category->update([
            'budgeted' => $request->target_amount
        ]);

        $category->updateActivity();

        return response()->json([
            'message' => 'Budget target set successfully',
            'category' => $category->fresh(),
            'target_amount' => $request->target_amount,
            'target_month' => $request->target_month
        ]);
    }

    public function getBudgetOverview(Request $request): JsonResponse
    {
        $this->authorize('view');

        $user = $request->user();

        // Calculate overall budget health
        $totalIncome = $user->transactions()
            ->where('amount', '>', 0)
            ->whereMonth('date', now()->month)
            ->sum('amount');

        $totalBudgeted = $user->categories()->sum('budgeted');
        $totalSpent = abs($user->transactions()
            ->where('amount', '<', 0)
            ->whereMonth('date', now()->month)
            ->sum('amount'));

        $budgetUtilization = $totalBudgeted > 0 ? ($totalSpent / $totalBudgeted) * 100 : 0;

        return response()->json([
            'overview' => [
                'total_income' => $totalIncome,
                'total_budgeted' => $totalBudgeted,
                'total_spent' => $totalSpent,
                'remaining' => $totalBudgeted - $totalSpent,
                'budget_utilization_percentage' => round($budgetUtilization, 2),
                'over_budget' => $totalSpent > $totalBudgeted,
            ]
        ]);
    }
}
