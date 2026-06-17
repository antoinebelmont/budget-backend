<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\CsvExportService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    use AuthorizesRequests;
    public function index(Request $request):JsonResponse
    {
        $query = $request->user()->transactions()->with('account', 'category', 'payee');

        if ($request->has('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $perPage = $request->integer('per_page', 50);
        $perPage = min(max($perPage, 10), 100);

        $transactions = $query->orderBy('date', 'desc')->paginate($perPage);

        return response()->json($transactions);
    }

    public function store(Request $request):JsonResponse
    {
        $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'date' => 'required|date',
            'amount' => 'required|numeric',
            'payee_id' => 'nullable|exists:payees,id',
            'category_id' => 'nullable|exists:categories,id',
            'memo' => 'nullable|string',
            'cleared' => 'sometimes|in:cleared,uncleared,reconciled'
        ]);

        $transaction = $request->user()->transactions()->create($request->all());
        if ($transaction->category) {
            $transaction->category->updateActivity();
        }
        if ($transaction->account) {
            $transaction->account->updateBalance();
        }
        return response()->json(['transaction' => $transaction->load('account', 'category', 'payee')], 201);
    }
    public function update(Request $request, Transaction $transaction):JsonResponse
    {
        $this->authorize('update', $transaction);

        $request->validate([
            'account_id' => 'sometimes|exists:accounts,id',
            'date' => 'sometimes|date',
            'amount' => 'sometimes|numeric',
            'payee_id' => 'sometimes|exists:payees,id',
            'category_id' => 'sometimes|exists:categories,id',
            'memo' => 'sometimes|string',
            'cleared' => 'sometimes|in:cleared,uncleared,reconciled'
        ]);

        $transaction->update($request->all());

        return response()->json(['transaction' => $transaction->load('account', 'category', 'payee')]);
    }

    public function destroy(Transaction $transaction): JsonResponse
    {
        $this->authorize('delete', $transaction);

        $transaction->delete();

        return response()->json(['message' => 'Account deleted successfully'], 204);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:transactions,id'
        ]);

        $user = $request->user();
        $ids = $request->ids;

        $transactions = Transaction::whereIn('id', $ids)
            ->where('user_id', $user->id)
            ->get();

        foreach ($transactions as $transaction) {
            $this->authorize('delete', $transaction);
            $transaction->delete();
        }

        return response()->json(['message' => 'Transactions deleted successfully'], 200);
    }

    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:transactions,id',
            'cleared' => 'required|in:cleared,uncleared,reconciled'
        ]);

        $user = $request->user();

        $transactions = Transaction::whereIn('id', $request->ids)
            ->where('user_id', $user->id)
            ->get();

        foreach ($transactions as $transaction) {
            $this->authorize('update', $transaction);
            $transaction->update(['cleared' => $request->cleared]);
        }

        return response()->json(['transactions' => $transactions->load('account', 'category', 'payee')]);
    }

    public function bulkUpdateCategory(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:transactions,id',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        $user = $request->user();

        $transactions = Transaction::whereIn('id', $request->ids)
            ->where('user_id', $user->id)
            ->get();

        foreach ($transactions as $transaction) {
            $this->authorize('update', $transaction);
            $transaction->update(['category_id' => $request->category_id]);
        }

        return response()->json(['transactions' => $transactions->load('account', 'category', 'payee')]);
    }

    public function createTransactionGoal(Request $request): JsonResponse
    {
        $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'date' => 'required|date',
            'amount' => 'required|numeric',
            'payee_id' => 'nullable|exists:payees,id',
            'category_id' => 'sometimes|exists:categories,id',
            'goal_id' => 'required|exists:goals,id',
            'memo' => 'nullable|string',
            'cleared' => 'sometimes|in:cleared,uncleared,reconciled'
        ]);

        $transaction = $request->user()->transactions()->create($request->all());
        if ($transaction->category) {
            $transaction->category->updateActivity();
        }
        if ($transaction->account) {
            $transaction->account->updateBalance();
        }
        return response()->json(['transaction' => $transaction->load('account', 'category', 'payee')], 201);
    }

    public function export(Request $request, CsvExportService $csvService)
    {
        $request->validate([
            'account_id' => 'nullable|exists:accounts,id',
            'category_id' => 'nullable|exists:categories,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $query = $request->user()->transactions()->with('account', 'category', 'payee');

        if ($request->has('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('start_date')) {
            $query->where('date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->where('date', '<=', $request->end_date);
        }

        $transactions = $query->orderBy('date', 'asc')->get();

        $includeAccount = !$request->has('account_id');
        $csvContent = $csvService->generateCsv($transactions, $includeAccount);

        $filename = 'transactions_' . date('Y-m-d') . '.csv';

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
