<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\BudgetController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CategoryGroupController;
use App\Http\Controllers\Api\GoalController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\PayeeController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\UserPreferenceController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\ReportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [PasswordResetController::class, 'forgotPassword']);
    Route::post('reset-password', [PasswordResetController::class, 'resetPassword']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('refresh', [AuthController::class, 'refresh']);
    });
    // User Preferences
    Route::get('user/preferences', [UserPreferenceController::class, 'index']);
    Route::put('user/preferences', [UserPreferenceController::class, 'update']);

    // Accounts
    Route::get('accounts/for-sidebar', [AccountController::class, 'forSidebar']);
    Route::apiResource('accounts', AccountController::class);

    // Budget
    Route::put('categories/{category}/budget', [BudgetController::class, 'updateCategoryBudget']);
    Route::get('budget', [BudgetController::class, 'index']);

    // Transactions
    Route::post('transaction-goal', [TransactionController::class, 'createTransactionGoal']);
    Route::get('transactions/export', [TransactionController::class, 'export']);
    Route::post('transactions/import', ImportController::class);
    Route::apiResource('transactions', TransactionController::class);

    // Categories
    Route::apiResource('categories', CategoryController::class);
    Route::put('categories/{category}/move', [CategoryController::class, 'move']);
    Route::put('categories/reorder', [CategoryController::class, 'reorder']);

    // Category Groups
    Route::put('category-groups/reorder', [CategoryGroupController::class, 'reorder']);
    Route::apiResource('category-groups', CategoryGroupController::class);

    // Payees
    Route::apiResource('payees', PayeeController::class);
    Route::get('payees/suggestions', [PayeeController::class, 'suggestions']);

    // Goals
    Route::apiResource('goals', GoalController::class);

    // Reports
    Route::get('reports/spending', [ReportController::class, 'spending']);
    Route::get('reports/net-worth', [ReportController::class, 'netWorth']);
    Route::get('reports/income-vs-expense', [ReportController::class, 'incomeVsExpense']);
    Route::get('reports/budget-vs-actual', [ReportController::class, 'budgetVsActual']);
    Route::get('reports/cash-flow', [ReportController::class, 'cashFlow']);
    Route::get('reports/saved', [ReportController::class, 'savedReports']);
    Route::post('reports/saved', [ReportController::class, 'saveReport']);
    Route::delete('reports/saved/{id}', [ReportController::class, 'deleteSavedReport']);
})->withoutMiddleware(\App\Http\Middleware\EnsureUserIsActive::class);

// Authentication routes
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->post('logout', [AuthController::class, 'logout']);
