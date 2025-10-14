<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
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

        $transactions = $query->orderBy('date', 'desc')->paginate(50);

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

        return response()->json(['transaction' => $transaction]);
    }

    public function destroy(Transaction $transaction): JsonResponse
    {
        $this->authorize('delete', $transaction);

        $transaction->delete();

        return response()->json(['message' => 'Account deleted successfully'], 204);
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
}
