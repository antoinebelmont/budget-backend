<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Goal;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoalController extends Controller
{
    use AuthorizesRequests;
    public function index(Request $request): JsonResponse
    {
        \Log::info('🔵 Fetching goals for user: ' . $request->user()->id);

        $goals = Goal::whereHas('category', function($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        })
            ->with(['category' => function($query) {
                // ✅ FIX: Force fresh category data with transactions
                $query->withSum('transactions', 'amount');
            }])
            ->get();

        foreach ($goals as $goal) {
            if ($goal->category) {
                \Log::info('🟢 Goal: ' . $goal->id);
                \Log::info('  Category: ' . $goal->category->id);
                \Log::info('  Budgeted: ' . $goal->category->budgeted);
                \Log::info('  Transactions Sum: ' . $goal->category->transactions->sum('amount'));
                \Log::info('  Available: ' . $goal->category->available);

                $goal->category->updateActivity();
                $goal->category->refresh();

                \Log::info('  Available After Refresh: ' . $goal->category->available);
            }
        }

        return response()->json(['goals' => $goals]);
    }

    public function store(Request $request):JsonResponse
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'type' => 'required|in:target_balance,target_date,monthly_funding',
            'target_amount' => 'required_unless:type,monthly_funding|numeric|min:0',
            'target_date' => 'required_if:type,target_date|date|after:today',
            'monthly_amount' => 'required_if:type,monthly_funding|numeric|min:0'
        ]);

        // Verify category belongs to user
        $category = Category::where('id', $request->category_id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        // Check if goal already exists for this category
        if ($category->goals()->exists()) {
            return response()->json([
                'message' => 'A goal already exists for this category'
            ], 422);
        }

        $goal = Goal::create($request->all());

        return response()->json(['goal' => $goal->load('category')], 201);
    }

    public function show(Goal $goal):JsonResponse
    {
        $this->authorize('view', $goal);

        $goal->load(['category' => function($query) {
            $query->with('transactions'); // Ensure transactions are loaded
        }]);

        return response()->json([
            'goal' => $goal,
            'progress' => [
                'percentage' => $goal->progress_percentage,
                'remaining_amount' => $goal->remaining_amount,
                'months_remaining' => $goal->months_remaining,
                'suggested_monthly' => $goal->suggested_monthly_amount,
            ]
        ]);
    }

    public function update(Request $request, Goal $goal):JsonResponse
    {
        $this->authorize('update', $goal);

        $request->validate([
            'type' => 'sometimes|in:target_balance,target_date,monthly_funding',
            'target_amount' => 'required_unless:type,monthly_funding|numeric|min:0',
            'target_date' => 'required_if:type,target_date|date|after:today',
            'monthly_amount' => 'required_if:type,monthly_funding|numeric|min:0'
        ]);

        $goal->update($request->all());

        return response()->json(['goal' => $goal->load('category')]);
    }

    public function destroy(Goal $goal):JsonResponse
    {
        $this->authorize('delete', $goal);

        $goal->delete();

        return response()->json(['message' => 'Goal deleted successfully']);
    }
}
