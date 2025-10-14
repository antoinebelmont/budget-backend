<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function spending(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'category_id' => 'sometimes|exists:categories,id'
        ]);

        $query = $request->user()->transactions()
            ->with('category', 'payee')
            ->where('amount', '<', 0)
            ->whereBetween('date', [$request->start_date, $request->end_date]);

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $transactions = $query->get();

        $spendingByCategory = $transactions->groupBy('category.name')
            ->map(function ($group) {
                return [
                    'category' => $group->first()->category->name ?? 'Uncategorized',
                    'total' => abs($group->sum('amount')),
                    'count' => $group->count()
                ];
            })
            ->sortByDesc('total')
            ->values();

        $spendingByPayee = $transactions->groupBy('payee.name')
            ->map(function ($group) {
                return [
                    'payee' => $group->first()->payee->name ?? 'Unknown',
                    'total' => abs($group->sum('amount')),
                    'count' => $group->count()
                ];
            })
            ->sortByDesc('total')
            ->take(10)
            ->values();

        return response()->json([
            'total_spent' => abs($transactions->sum('amount')),
            'transaction_count' => $transactions->count(),
            'by_category' => $spendingByCategory,
            'top_payees' => $spendingByPayee,
            'period' => [
                'start' => $request->start_date,
                'end' => $request->end_date
            ]
        ]);
    }

    public function netWorth(Request $request): JsonResponse
    {
        $accounts = $request->user()->accounts()->get();

        $netWorth = $accounts->sum(function ($account) {
            return $account->type === 'credit_card' ? -$account->balance : $account->balance;
        });

        $accountBreakdown = $accounts->map(function ($account) {
            return [
                'name' => $account->name,
                'type' => $account->type,
                'balance' => $account->balance,
                'contribution' => $account->type === 'credit_card' ? -$account->balance : $account->balance
            ];
        });

        return response()->json([
            'net_worth' => $netWorth,
            'accounts' => $accountBreakdown,
            'as_of' => now()->toDateString()
        ]);
    }

    public function incomeVsExpense(Request $request): JsonResponse
    {
        $request->validate([
            'months' => 'sometimes|integer|min:1|max:24'
        ]);

        $months = $request->get('months', 12);
        $startDate = now()->subMonths($months)->startOfMonth();

        $monthlyData = DB::table('transactions')
            ->where('user_id', $request->user()->id)
            ->where('date', '>=', $startDate)
            ->select(
                DB::raw('YEAR(date) as year'),
                DB::raw('MONTH(date) as month'),
                DB::raw('SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as income'),
                DB::raw('SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as expenses')
            )
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        $formattedData = $monthlyData->map(function ($row) {
            return [
                'month' => Carbon::create($row->year, $row->month, 1)->format('M Y'),
                'income' => (float) $row->income,
                'expenses' => (float) $row->expenses,
                'net' => (float) $row->income - (float) $row->expenses
            ];
        });

        return response()->json([
            'monthly_data' => $formattedData,
            'summary' => [
                'total_income' => $monthlyData->sum('income'),
                'total_expenses' => $monthlyData->sum('expenses'),
                'net_income' => $monthlyData->sum('income') - $monthlyData->sum('expenses'),
                'average_monthly_income' => $monthlyData->avg('income'),
                'average_monthly_expenses' => $monthlyData->avg('expenses')
            ]
        ]);
    }
    public function budgetVsActual(Request $request): JsonResponse
    {
        $request->validate([
            'month' => 'required|date_format:Y-m'
        ]);

        $month = $request->month;
        $user = $request->user();

        // Get all category groups with their categories
        $categoryGroups = $user->categoryGroups()
            ->with(['categories' => function($query) {
                $query->where('hidden', false);
            }])
            ->where('hidden', false)
            ->orderBy('sort_order')
            ->get();

        $categories = [];
        $totalBudgeted = 0;
        $totalActual = 0;
        $overBudgetCount = 0;
        $underBudgetCount = 0;

        foreach ($categoryGroups as $group) {
            foreach ($group->categories as $category) {
                // Get the budgeted amount for this category
                $budgeted = $category->budgeted;

                // Calculate actual spending for this month
                $actual = $user->transactions()
                    ->where('category_id', $category->id)
                    ->whereYear('date', substr($month, 0, 4))
                    ->whereMonth('date', substr($month, 5, 2))
                    ->sum('amount');

                // For expenses, make positive for comparison
                $actual = abs($actual);

                $difference = $budgeted - $actual;
                $percentage = $budgeted > 0 ? (($actual / $budgeted) * 100) : 0;

                if ($difference < 0) {
                    $overBudgetCount++;
                } else if ($actual > 0 && $difference > 0) {
                    $underBudgetCount++;
                }

                $totalBudgeted += $budgeted;
                $totalActual += $actual;

                $categories[] = [
                    'id' => $category->id,
                    'name' => $category->name,
                    'category_group' => $group->name,
                    'budgeted' => (float) $budgeted,
                    'actual' => (float) $actual,
                    'difference' => (float) $difference,
                    'percentage' => round($percentage, 2),
                ];
            }
        }

        return response()->json([
            'month' => $month,
            'categories' => $categories,
            'summary' => [
                'total_budgeted' => (float) $totalBudgeted,
                'total_actual' => (float) $totalActual,
                'total_difference' => (float) ($totalBudgeted - $totalActual),
                'over_budget_count' => $overBudgetCount,
                'under_budget_count' => $underBudgetCount,
            ]
        ]);
    }
    public function cashFlow(Request $request): JsonResponse
    {
        $request->validate([
            'months' => 'sometimes|integer|min:1|max:24'
        ]);

        $months = $request->get('months', 12);
        $user = $request->user();

        $startDate = now()->subMonths($months)->startOfMonth();
        $endDate = now()->endOfMonth();

        // Get all transactions grouped by month
        $monthlyData = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $monthStart = $currentDate->copy()->startOfMonth();
            $monthEnd = $currentDate->copy()->endOfMonth();

            // Calculate starting balance (all transactions before this month)
            $startingBalance = $user->transactions()
                ->where('date', '<', $monthStart)
                ->sum('amount');

            // Calculate income and expenses for this month
            $monthTransactions = $user->transactions()
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->get();

            $income = $monthTransactions->where('amount', '>', 0)->sum('amount');
            $expenses = $monthTransactions->where('amount', '<', 0)->sum('amount');

            $netChange = $income + $expenses; // expenses are negative
            $endingBalance = $startingBalance + $netChange;

            $monthlyData[] = [
                'month' => $currentDate->format('Y-m'),
                'starting_balance' => (float) $startingBalance,
                'income' => (float) $income,
                'expenses' => (float) $expenses,
                'ending_balance' => (float) $endingBalance,
                'net_change' => (float) $netChange,
            ];

            $currentDate->addMonth();
        }

        // Calculate summary statistics
        $totalIncome = collect($monthlyData)->sum('income');
        $totalExpenses = collect($monthlyData)->sum('expenses');
        $avgIncome = $totalIncome / count($monthlyData);
        $avgExpenses = $totalExpenses / count($monthlyData);
        $avgNetChange = ($totalIncome + $totalExpenses) / count($monthlyData);

        // Determine trend
        $firstMonth = $monthlyData[0] ?? null;
        $lastMonth = end($monthlyData);
        $trend = 'stable';

        if ($firstMonth && $lastMonth) {
            $balanceChange = $lastMonth['ending_balance'] - $firstMonth['starting_balance'];
            if ($balanceChange > ($avgIncome * 0.1)) {
                $trend = 'positive';
            } elseif ($balanceChange < ($avgIncome * -0.1)) {
                $trend = 'negative';
            }
        }

        return response()->json([
            'months' => $monthlyData,
            'summary' => [
                'average_income' => $avgIncome,
                'average_expenses' => $avgExpenses,
                'average_net_change' => $avgNetChange,
                'trend' => $trend,
            ]
        ]);
    }

    public function savedReports(Request $request): JsonResponse
    {
        $reports = $request->user()->savedReports()->latest()->get();
        return response()->json(['reports' => $reports]);
    }

    public function saveReport(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:spending,income_vs_expense,net_worth,budget_vs_actual,category_trend,cash_flow',
            'filters' => 'required|array',
        ]);

        $report = $request->user()->savedReports()->create($request->all());

        return response()->json(['report' => $report], 201);
    }

    public function deleteSavedReport(Request $request, $id): JsonResponse
    {
        $report = $request->user()->savedReports()->findOrFail($id);
        $report->delete();

        return response()->json(['message' => 'Report deleted successfully']);
    }
}
