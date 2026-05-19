<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class AccountController extends Controller
{
    use AuthorizesRequests;
    public function index(Request $request)
    {
        return response()->json([
            'accounts' => $request->user()->accounts()->with('transactions')->get()
        ]);
    }

    public function store(Request $request):JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:checking,savings,credit_card,investment',
            'balance' => 'required|numeric'
        ]);

        $account = $request->user()->accounts()->create($request->all());
        $transaction = $request->user()->transactions()->create([
            'account_id' => $account->id,
            'date' => now(),
            'amount' => $account->balance,
            'cleared' => 'cleared',
            'approved' => 1
        ]);
        return response()->json(['account' => $account], 201);
    }

    public function show(Request $request, Account $account):JsonResponse
    {
        $this->authorize('view', $account);

        return response()->json([
            'account' => $account->load('transactions.category', 'transactions.payee')
        ]);
    }

    public function update(Request $request, Account $account):JsonResponse
    {
        $this->authorize('update', $account);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:checking,savings,credit_card,investment',
            'closed' => 'sometimes|boolean'
        ]);

        $account->update($request->all());

        return response()->json(['account' => $account]);
    }

    public function destroy(Account $account)
    {
        $this->authorize('delete', $account);

        $account->delete();

        return response()->json(['message' => 'Account deleted successfully'], 204);
    }

    public function forSidebar(Request $request): JsonResponse
    {
        $accounts = $request->user()->accounts()
            ->withCount('transactions')
            ->orderBy('name')
            ->get()
            ->map(function ($account) {
                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'type' => $account->type,
                    'transaction_count' => $account->transactions_count,
                    'has_transactions' => $account->transactions_count > 0,
                ];
            });

        return response()->json(['accounts' => $accounts]);
    }
}
